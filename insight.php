<?php
// 세션 시작 및 검증
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';

// 전역 오류 플래그
$error_occurred = false;
$error_message = '';

// DB 연결 검증
if (!$conn) {
    error_log("DB Connection failed: " . mysqli_connect_error());
    die("서비스에 일시적으로 접속할 수 없습니다. 잠시 후 다시 시도해주세요.");
}

// 세션 검증 강화
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    session_destroy();
    header("Location: index.php?error=invalid_session");
    exit();
}

// 세션 타임아웃 체크 (1시간)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: index.php?error=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

$user_id = intval($_SESSION['user_id']);
$username = $_SESSION['username'] ?? 'Guest';

// 기본값 초기화
$total_answers = 0;
$days_elapsed = 0;
$consecutive_days = 0;
$this_month_answers = 0;
$category_stats = [];
$recent_answers = [];

// 1. 전체 답변 수 조회
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM answers WHERE user_id = ?");
if (!$stmt) {
    error_log("Prepare failed (total_answers): " . $conn->error);
    $error_occurred = true;
} else {
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (total_answers): " . $stmt->error);
        $error_occurred = true;
    } else {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $total_answers = intval($row['total']);
        }
    }
    $stmt->close();
}

// 2. 가입일 조회 및 경과일 계산
$stmt = $conn->prepare("SELECT created_at FROM users WHERE id = ?");
if (!$stmt) {
    error_log("Prepare failed (user_info): " . $conn->error);
    $error_occurred = true;
} else {
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (user_info): " . $stmt->error);
        $error_occurred = true;
    } else {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            try {
                $join_date = new DateTime($row['created_at']);
                $today = new DateTime();
                $days_elapsed = intval($today->diff($join_date)->days);
            } catch (Exception $e) {
                error_log("Date parsing error: " . $e->getMessage());
                $days_elapsed = 0;
            }
        }
    }
    $stmt->close();
}

// 3. 연속 작성일 계산
$stmt = $conn->prepare("
    SELECT COUNT(*) as consecutive
    FROM (
        SELECT answer_date, 
        ROW_NUMBER() OVER (ORDER BY answer_date DESC) as rn
        FROM answers
        WHERE user_id = ?
        GROUP BY answer_date
    ) as ranked
    WHERE DATE_SUB(CURRENT_DATE, INTERVAL rn DAY) <= answer_date
");
if (!$stmt) {
    error_log("Prepare failed (consecutive_days): " . $conn->error);
    $error_occurred = true;
} else {
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (consecutive_days): " . $stmt->error);
        $error_occurred = true;
    } else {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $consecutive_days = intval($row['consecutive'] ?? 0);
        }
    }
    $stmt->close();
}

// 4. 카테고리별 통계
$stmt = $conn->prepare("
    SELECT category, COUNT(*) as count
    FROM answers
    WHERE user_id = ?
    GROUP BY category
    ORDER BY count DESC
");
if (!$stmt) {
    error_log("Prepare failed (category_stats): " . $conn->error);
    $error_occurred = true;
} else {
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (category_stats): " . $stmt->error);
        $error_occurred = true;
    } else {
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $category_stats[] = [
                    'category' => $row['category'] ?? 'Unknown',
                    'count' => intval($row['count'])
                ];
            }
        }
    }
    $stmt->close();
}

// 5. 최근 답변 조회
$stmt = $conn->prepare("
    SELECT q.question, a.answer, a.answer_date, a.category
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    WHERE a.user_id = ?
    ORDER BY a.answer_date DESC
    LIMIT 5
");
if (!$stmt) {
    error_log("Prepare failed (recent_answers): " . $conn->error);
    $error_occurred = true;
} else {
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (recent_answers): " . $stmt->error);
        $error_occurred = true;
    } else {
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_answers[] = [
                    'question' => $row['question'] ?? '',
                    'answer' => $row['answer'] ?? '',
                    'answer_date' => $row['answer_date'] ?? date('Y-m-d'),
                    'category' => $row['category'] ?? 'Unknown'
                ];
            }
        }
    }
    $stmt->close();
}

// 6. 이번 달 답변 수
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');
$stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM answers
    WHERE user_id = ? AND answer_date BETWEEN ? AND ?
");
if (!$stmt) {
    error_log("Prepare failed (this_month): " . $conn->error);
    $error_occurred = true;
} else {
    $stmt->bind_param("iss", $user_id, $this_month_start, $this_month_end);
    if (!$stmt->execute()) {
        error_log("Execute failed (this_month): " . $stmt->error);
        $error_occurred = true;
    } else {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $this_month_answers = intval($row['count']);
        }
    }
    $stmt->close();
}

// 일평균 계산 (0으로 나누기 방지)
$avg_per_day = $days_elapsed > 0 ? round($total_answers / $days_elapsed, 1) : 0;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q&A 다이어리 - 인사이트</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #5b7cfa;
            --primary-dark: #4c63dd;
            --bg-light: #f5f7fb;
            --text-light: #333;
            --card-light: #ffffff;
            --border-light: #e0e0e0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-light);
            min-height: 100vh;
            padding: 20px;
        }
        
        .error-banner {
            background: #fee2e2;
            color: #dc2626;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            border: 2px solid #fecaca;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        h1 {
            color: var(--text-light);
            font-size: 28px;
            font-weight: 700;
        }
        
        .header-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .username {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.7;
        }
        
        .logout-btn {
            text-decoration: none;
            color: white;
            background: linear-gradient(135deg, #5b7cfa 0%, #667eea 100%);
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(91, 124, 250, 0.3);
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
            margin-bottom: 40px;
            justify-content: center;
            flex-wrap: wrap;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .nav-links a {
            padding: 12px 20px;
            background: var(--card-light);
            color: var(--primary);
            text-decoration: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .nav-links a.active {
            background: var(--primary);
            color: white;
        }
        
        .container {
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .section {
            background: var(--card-light);
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }
        
        h2 {
            color: var(--text-light);
            font-size: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid rgba(91, 124, 250, 0.1);
            padding-bottom: 12px;
            font-weight: 700;
        }
        
        /* 슬라이더 컨테이너 */
        .slider-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
        }
        
        .stats-slider {
            display: flex;
            gap: 16px;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 8px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #5b7cfa 0%, #748cfd 100%);
            color: white;
            padding: 24px;
            border-radius: 14px;
            text-align: center;
            transition: all 0.3s;
            flex-shrink: 0;
            min-width: 180px;
            cursor: pointer;
            user-select: none;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(91, 124, 250, 0.2);
        }
        
        .stat-card.alt {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
        }
        
        .stat-card.alt2 {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        }
        
        .stat-card.alt3 {
            background: linear-gradient(135deg, #a78bfa 0%, #c4b5fd 100%);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 600;
        }
        
        /* 슬라이더 버튼 */
        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.95);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s;
            z-index: 10;
        }
        
        .slider-btn:hover {
            background: white;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            transform: translateY(-50%) scale(1.1);
        }
        
        .slider-btn:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .slider-btn.prev {
            left: 10px;
        }
        
        .slider-btn.next {
            right: 10px;
        }
        
        .slider-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .slider-btn:disabled:hover {
            transform: translateY(-50%) scale(1);
        }
        
        /* 인디케이터 */
        .slider-indicators {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
        }
        
        .indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #d1d5db;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .indicator.active {
            background: var(--primary);
            width: 24px;
            border-radius: 4px;
        }
        
        /* 모바일 반응형 */
        @media (max-width: 768px) {
            .stat-card {
                min-width: calc(100% - 80px);
            }
            
            .slider-btn {
                width: 36px;
                height: 36px;
                font-size: 18px;
            }
        }
        
        .category-list {
            list-style: none;
        }
        
        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .category-item:last-child {
            border-bottom: none;
        }
        
        .category-name {
            color: var(--text-light);
            font-weight: 600;
            font-size: 15px;
        }
        
        .category-count {
            background: rgba(91, 124, 250, 0.1);
            color: var(--primary);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }
        
        .recent-answers {
            display: flex;
            overflow-x: auto;
            gap: 16px;
            padding-bottom: 20px;
            scroll-behavior: smooth;
        }
        
        .recent-answers::-webkit-scrollbar {
            height: 8px;
        }
        
        .recent-answers::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 10px;
        }
        
        .recent-answers::-webkit-scrollbar-thumb {
            background: #5b7cfa;
            border-radius: 10px;
        }
        
        .answer-item {
            background: rgba(91, 124, 250, 0.05);
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid var(--primary);
            transition: all 0.3s;
            flex-shrink: 0;
            min-width: 320px;
            max-width: 320px;
        }
        
        .answer-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(91, 124, 250, 0.15);
        }
        
        .answer-date {
            color: var(--text-light);
            opacity: 0.6;
            font-size: 13px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .answer-category {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .answer-question {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .answer-text {
            color: var(--text-light);
            font-size: 14px;
            line-height: 1.7;
            opacity: 0.85;
        }
        
        .empty-message {
            text-align: center;
            color: var(--text-light);
            opacity: 0.6;
            padding: 40px 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php if ($error_occurred): ?>
    <div class="error-banner">
        ⚠️ 일부 데이터를 불러오는 중 문제가 발생했습니다. 페이지를 새로고침해주세요.
    </div>
    <?php endif; ?>
    
    <div class="header">
        <h1>📖 Q&A 다이어리</h1>
        <div class="header-right">
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="logout-btn">로그아웃</a>
        </div>
    </div>
    
    <div class="nav-links">
        <a href="main.php">오늘의 질문</a>
        <a href="calendar.php">캘린더</a>
        <a href="insight.php" class="active">인사이트</a>
        <a href="community.php" class="active">커뮤니티</a>
    </div>
    
    <div class="container">
        <div class="section">
            <h2>📊 통계</h2>
            <div class="slider-wrapper">
                <div class="stats-slider" id="statsSlider">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_answers; ?></div>
                        <div class="stat-label">전체 작성</div>
                    </div>
                    <div class="stat-card alt">
                        <div class="stat-number"><?php echo $this_month_answers; ?></div>
                        <div class="stat-label">이번 달</div>
                    </div>
                    <div class="stat-card alt2">
                        <div class="stat-number"><?php echo $consecutive_days; ?></div>
                        <div class="stat-label">연속일</div>
                    </div>
                    <div class="stat-card alt3">
                        <div class="stat-number"><?php echo $avg_per_day; ?></div>
                        <div class="stat-label">일평균</div>
                    </div>
                </div>
    </div>
        </div>
        
        <div class="section">
            <h2>📁 카테고리별 작성</h2>
            <?php if (count($category_stats) > 0): ?>
                <ul class="category-list">
                    <?php foreach ($category_stats as $stat): ?>
                        <li class="category-item">
                            <span class="category-name"><?php echo htmlspecialchars($stat['category']); ?></span>
                            <span class="category-count"><?php echo $stat['count']; ?>개</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-message">아직 작성된 답변이 없습니다.</div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>✨ 최근 답변</h2>
            <?php if (count($recent_answers) > 0): ?>
                <div class="recent-answers">
                    <?php foreach ($recent_answers as $answer): ?>
                        <div class="answer-item">
                            <div class="answer-date">📅 <?php echo date('Y년 m월 d일', strtotime($answer['answer_date'])); ?></div>
                            <span class="answer-category"><?php echo htmlspecialchars($answer['category']); ?></span>
                            <div class="answer-question">❓ <?php echo htmlspecialchars($answer['question']); ?></div>
                            <div class="answer-text"><?php echo nl2br(htmlspecialchars($answer['answer'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-message">아직 작성된 답변이 없습니다.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let currentSlide = 0;
        const slider = document.getElementById('statsSlider');
        const cards = slider.querySelectorAll('.stat-card');
        const totalCards = cards.length;
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const indicatorsContainer = document.getElementById('indicators');
        
        // 카드 너비 + 간격
        function getCardWidth() {
            return cards[0].offsetWidth + 16;
        }
        
        // 한 번에 보이는 카드 수 계산
        function getVisibleCards() {
            const containerWidth = slider.parentElement.offsetWidth;
            const cardWidth = getCardWidth();
            return Math.floor(containerWidth / cardWidth);
        }
        
        // 인디케이터 생성 (카드 개수만큼)
        function createIndicators() {
            indicatorsContainer.innerHTML = '';
            
            if (totalCards <= 1) {
                indicatorsContainer.style.display = 'none';
                return;
            }
            
            indicatorsContainer.style.display = 'flex';
            for (let i = 0; i < totalCards; i++) {
                const indicator = document.createElement('div');
                indicator.className = 'indicator';
                if (i === 0) indicator.classList.add('active');
                indicator.onclick = () => goToSlide(i);
                indicatorsContainer.appendChild(indicator);
            }
        }
        
        // 슬라이드 이동 (1개씩)
        function slideStats(direction) {
            currentSlide += direction;
            currentSlide = Math.max(0, Math.min(currentSlide, totalCards - 1));
            updateSlider();
        }
        
        // 특정 슬라이드로 이동
        function goToSlide(index) {
            currentSlide = index;
            updateSlider();
        }
        
        // 슬라이더 업데이트
        function updateSlider() {
            const cardWidth = getCardWidth();
            const offset = -currentSlide * cardWidth;
            slider.style.transform = `translateX(${offset}px)`;
            
            // 버튼 상태 업데이트
            prevBtn.disabled = currentSlide === 0;
            nextBtn.disabled = currentSlide >= totalCards - 1;
            
            // 인디케이터 업데이트
            document.querySelectorAll('.indicator').forEach((ind, idx) => {
                ind.classList.toggle('active', idx === currentSlide);
            });
        }
        
        // 터치/마우스 드래그 지원
        let startX = 0;
        let currentX = 0;
        let isDragging = false;
        let startTransform = 0;
        
        slider.addEventListener('mousedown', dragStart);
        slider.addEventListener('touchstart', dragStart);
        
        slider.addEventListener('mousemove', dragging);
        slider.addEventListener('touchmove', dragging);
        
        slider.addEventListener('mouseup', dragEnd);
        slider.addEventListener('mouseleave', dragEnd);
        slider.addEventListener('touchend', dragEnd);
        
        function dragStart(e) {
            isDragging = true;
            startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX;
            const transform = window.getComputedStyle(slider).transform;
            startTransform = transform !== 'none' ? parseFloat(transform.split(',')[4]) : 0;
            slider.style.transition = 'none';
        }
        
        function dragging(e) {
            if (!isDragging) return;
            e.preventDefault();
            currentX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
            const diff = currentX - startX;
            slider.style.transform = `translateX(${startTransform + diff}px)`;
        }
        
        function dragEnd(e) {
            if (!isDragging) return;
            isDragging = false;
            slider.style.transition = 'transform 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            
            const diff = currentX - startX;
            const threshold = 50;
            
            if (Math.abs(diff) > threshold) {
                if (diff > 0) {
                    slideStats(-1);
                } else {
                    slideStats(1);
                }
            } else {
                updateSlider();
            }
        }
        
        // 초기화 및 리사이즈 처리
        function init() {
            createIndicators();
            updateSlider();
        }
        
        window.addEventListener('resize', () => {
            currentSlide = 0;
            init();
        });
        
        // 페이지 로드 시 초기화
        init();
    </script>
</body>
</html>