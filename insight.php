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
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $total_answers = intval($row['total']);
        }
    }
    $stmt->close();
}

// 2. 가입일 조회 및 경과일 계산
$stmt = $conn->prepare("SELECT created_at FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            try {
                $join_date = new DateTime($row['created_at']);
                $today = new DateTime();
                $days_elapsed = intval($today->diff($join_date)->days);
            } catch (Exception $e) {
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
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
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
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
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
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
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
if ($stmt) {
    $stmt->bind_param("iss", $user_id, $this_month_start, $this_month_end);
    if ($stmt->execute()) {
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
    * {margin:0; padding:0; box-sizing:border-box;}
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

/* ---------- HEADER ---------- */
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
  color: #fff;
  background: linear-gradient(135deg, #5b7cfa 0%, #667eea 100%);
  padding: 10px 18px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s;
}

.logout-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 16px rgba(91, 124, 250, 0.3);
}

/* ---------- NAV LINKS ---------- */
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

.nav-links a:hover,
.nav-links a.active {
  background: var(--primary);
  color: #fff;
}

/* ---------- CONTAINER & SECTIONS ---------- */
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
  font-size: 20px;
  margin-bottom: 20px;
  border-bottom: 2px solid rgba(91, 124, 250, 0.1);
  padding-bottom: 12px;
  font-weight: 700;
}

/* ---------- STATS SLIDER ---------- */
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
  flex-shrink: 0;
  min-width: 180px;
  cursor: pointer;
  user-select: none;
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

/* ---------- CATEGORY LIST ---------- */
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

/* ---------- RECENT ANSWERS ---------- */
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
  flex-shrink: 0;
  min-width: 320px;
  max-width: 320px;
}

.answer-date {
  font-size: 13px;
  opacity: 0.6;
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
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 10px;
}

.answer-text {
  font-size: 14px;
  line-height: 1.7;
  opacity: 0.85;
}

/* ---------- EMPTY MESSAGE ---------- */
.empty-message {
  text-align: center;
  color: var(--text-light);
  opacity: 0.6;
  padding: 40px 20px;
  font-size: 14px;
}

    /* 모바일 반응형 */
    @media (max-width: 768px) {
    body { padding: 10px; }
    .header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .header h1 { font-size: 22px; }
    .nav-links a { font-size: 13px; padding: 8px 14px; }
    .post-form, .post { padding: 15px; }
    .comment-form { flex-direction: column; }
    .comment-form input[type="text"], .comment-form input[type="submit"] { width: 100%; }
}

@media (max-width: 480px) {
    .header h1 { font-size: 20px; }
    .post h2 { font-size: 18px; }
    .nav-links { flex-direction: column; }
    .nav-links a { width: 100%; text-align: center; }
    .logout-btn { width: 100%; text-align: center; }
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
    <a href="community.php">커뮤니티</a>
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
</body>
</html>
