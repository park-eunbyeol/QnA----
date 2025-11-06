<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// 이전/다음 월 계산
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// 해당 월의 답변 데이터 조회
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

$stmt = $conn->prepare("SELECT answer_date FROM answers WHERE user_id = ? AND answer_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$answered_dates = [];
while ($row = $result->fetch_assoc()) {
    $answered_dates[] = (int)date('d', strtotime($row['answer_date']));
}
$stmt->close();

// 선택된 날짜의 답변 조회
$selected_date = $_GET['date'] ?? null;
$selected_answer = null;
$selected_question = null;

if ($selected_date) {
    $date_str = "$year-$month-" . str_pad($selected_date, 2, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("
        SELECT q.question, a.answer, a.category
        FROM answers a
        JOIN questions q ON a.question_id = q.id
        WHERE a.user_id = ? AND a.answer_date = ?
    ");
    $stmt->bind_param("is", $user_id, $date_str);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row) {
        $selected_question = $row['question'];
        $selected_answer = $row['answer'];
        $selected_category = $row['category'];
    }
    $stmt->close();
}

// 캘린더 생성
$first_day = mktime(0, 0, 0, $month, 1, $year);
$last_day = date('t', $first_day);
$day_of_week = date('w', $first_day);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q&A 다이어리 - 캘린더</title>
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
            --bg-dark: #1a1a2e;
            --text-light: #333;
            --text-dark: #e0e0e0;
            --card-light: #ffffff;
            --card-dark: #252a48;
            --border-light: #e0e0e0;
            --border-dark: #3a3f5a;
            --success: #10b981;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-light);
            min-height: 100vh;
            padding: 20px;
            transition: background 0.3s, color 0.3s;
        }
        
        body.dark-mode {
            background: var(--bg-dark);
            color: var(--text-dark);
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
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        h1 {
            color: var(--text-light);
            font-size: 28px;
            font-weight: 700;
        }
        
        body.dark-mode h1 {
            color: var(--text-dark);
        }
        
        .header-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .user-menu {
            display: flex;
            gap: 10px;
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
        
        body.dark-mode .nav-links a {
            background: var(--card-dark);
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
            background: var(--card-light);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 32px;
            margin-left: auto;
            margin-right: auto;
            transition: all 0.3s;
        }
        
        body.dark-mode .container {
            background: var(--card-dark);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .answer-detail {
            background: rgba(91, 124, 250, 0.05);
            padding: 24px;
            border-radius: 14px;
            border-left: 5px solid var(--primary);
            margin-bottom: 32px;
            animation: slideDown 0.3s ease-out;
        }
        
        body.dark-mode .answer-detail {
            background: rgba(91, 124, 250, 0.1);
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .answer-detail-date {
            color: var(--primary);
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .answer-detail-category {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 14px;
        }
        
        .answer-detail-question {
            color: var(--text-light);
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 14px;
        }
        
        body.dark-mode .answer-detail-question {
            color: var(--text-dark);
        }
        
        .answer-detail-text {
            color: var(--text-light);
            font-size: 14px;
            line-height: 1.7;
            white-space: pre-wrap;
            word-wrap: break-word;
            opacity: 0.85;
        }
        
        body.dark-mode .answer-detail-text {
            color: var(--text-dark);
        }
        
        .close-btn {
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 28px;
            cursor: pointer;
            padding: 4px 8px;
            float: right;
            opacity: 0.6;
            transition: all 0.2s;
        }
        
        body.dark-mode .close-btn {
            color: var(--text-dark);
        }
        
        .close-btn:hover {
            opacity: 1;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        
        .month-title {
            color: var(--text-light);
            font-size: 20px;
            font-weight: 700;
        }
        
        body.dark-mode .month-title {
            color: var(--text-dark);
        }
        
        .month-nav {
            display: flex;
            gap: 10px;
        }
        
        .month-nav a {
            padding: 10px 16px;
            background: rgba(91, 124, 250, 0.1);
            color: var(--primary);
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        
        body.dark-mode .month-nav a {
            background: rgba(91, 124, 250, 0.15);
        }
        
        .month-nav a:hover {
            background: var(--primary);
            color: white;
        }
        
        .calendar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }
        
        .weekday {
            background: rgba(91, 124, 250, 0.08);
            color: var(--primary);
            font-weight: 700;
            text-align: center;
            padding: 14px 8px;
            font-size: 13px;
        }
        
        body.dark-mode .weekday {
            background: rgba(91, 124, 250, 0.15);
        }
        
        .date {
            border: 1px solid var(--border-light);
            padding: 12px;
            text-align: center;
            height: 90px;
            vertical-align: top;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--card-light);
        }
        
        body.dark-mode .date {
            border-color: var(--border-dark);
            background: rgba(255, 255, 255, 0.02);
        }
        
        .date:hover:not(.empty) {
            background: rgba(91, 124, 250, 0.08);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(91, 124, 250, 0.15);
        }
        
        body.dark-mode .date:hover:not(.empty) {
            background: rgba(91, 124, 250, 0.15);
        }
        
        .date.empty {
            background: transparent;
            cursor: default;
            border-color: transparent;
        }
        
        .date.empty:hover {
            background: transparent;
            transform: none;
            box-shadow: none;
        }
        
        .date.answered {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        body.dark-mode .date.answered {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .date.answered:hover {
            background: rgba(16, 185, 129, 0.15);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        
        .date-number {
            font-weight: 700;
            color: var(--text-light);
            font-size: 16px;
        }
        
        body.dark-mode .date-number {
            color: var(--text-dark);
        }
        
        .date.answered .date-number {
            color: var(--success);
        }
        
        .check-mark {
            color: var(--success);
            font-size: 20px;
            margin-top: 4px;
            animation: popIn 0.3s ease-out;
        }
        
        @keyframes popIn {
            from {
                transform: scale(0.5);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            padding-top: 28px;
            border-top: 1px solid var(--border-light);
        }
        
        body.dark-mode .stats {
            border-top-color: var(--border-dark);
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, rgba(91, 124, 250, 0.08) 0%, rgba(102, 126, 234, 0.08) 100%);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        body.dark-mode .stat-item {
            background: linear-gradient(135deg, rgba(91, 124, 250, 0.15) 0%, rgba(102, 126, 234, 0.15) 100%);
        }
        
        .stat-item:hover {
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 6px;
            font-weight: 600;
            opacity: 0.7;
        }
        
        body.dark-mode .stat-label {
            color: var(--text-dark);
        }
        
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
    <div class="header">
        <div class="header-left">
            <h1>📖 Q&A 다이어리</h1>
        </div>
        <div class="header-right">
            <div class="user-menu">
                <span class="username"><?php echo htmlspecialchars($username); ?></span>
                <a href="logout.php" class="logout-btn">로그아웃</a>
            </div>
        </div>
    </div>
    
    <div class="nav-links">
        <a href="main.php">오늘의 질문</a>
        <a href="calendar.php" class="active">캘린더</a>
        <a href="insight.php">인사이트</a>
        <a href="community.php">커뮤니티</a>
    </div>
    
    <div class="container">
        <?php if ($selected_answer): ?>
            <div class="answer-detail">
                <button class="close-btn" onclick="window.location.href='calendar.php?year=<?php echo $year; ?>&month=<?php echo str_pad($month, 2, '0', STR_PAD_LEFT); ?>'">✕</button>
                <div class="answer-detail-date">
                    📅 <?php echo date('Y년 m월 d일', strtotime("$year-$month-" . str_pad($selected_date, 2, '0', STR_PAD_LEFT))); ?>
                </div>
                <span class="answer-detail-category"><?php echo htmlspecialchars($selected_category); ?></span>
                <div class="answer-detail-question">❓ <?php echo htmlspecialchars($selected_question); ?></div>
                <div class="answer-detail-text"><?php echo htmlspecialchars($selected_answer); ?></div>
            </div>
        <?php endif; ?>
        
        <div class="calendar-header">
            <div class="month-title"><?php echo date('Y년 m월', strtotime("$year-$month-01")); ?></div>
            <div class="month-nav">
                <a href="?year=<?php echo $prev_year; ?>&month=<?php echo str_pad($prev_month, 2, '0', STR_PAD_LEFT); ?>">← 이전</a>
                <a href="?year=<?php echo date('Y'); ?>&month=<?php echo date('m'); ?>">오늘</a>
                <a href="?year=<?php echo $next_year; ?>&month=<?php echo str_pad($next_month, 2, '0', STR_PAD_LEFT); ?>">다음 →</a>
            </div>
        </div>
        
        <table class="calendar">
            <thead>
                <tr>
                    <th class="weekday">일</th>
                    <th class="weekday">월</th>
                    <th class="weekday">화</th>
                    <th class="weekday">수</th>
                    <th class="weekday">목</th>
                    <th class="weekday">금</th>
                    <th class="weekday">토</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php
                    for ($i = 0; $i < $day_of_week; $i++) {
                        echo '<td class="date empty"></td>';
                    }
                    
                    $cells = $day_of_week;
                    
                    for ($day = 1; $day <= $last_day; $day++) {
                        if ($cells % 7 === 0 && $cells !== 0) {
                            echo '</tr><tr>';
                        }
                        
                        $is_answered = in_array($day, $answered_dates);
                        $date_class = $is_answered ? 'date answered' : 'date';
                        
                        if ($is_answered) {
                            echo '<td class="' . $date_class . '" onclick="window.location.href=\'?year=' . $year . '&month=' . str_pad($month, 2, '0', STR_PAD_LEFT) . '&date=' . $day . '\'">';
                        } else {
                            echo '<td class="' . $date_class . '">';
                        }
                        
                        echo '<div class="date-number">' . $day . '</div>';
                        if ($is_answered) {
                            echo '<div class="check-mark">✓</div>';
                        }
                        echo '</td>';
                        
                        $cells++;
                    }
                    
                    while ($cells % 7 !== 0) {
                        echo '<td class="date empty"></td>';
                        $cells++;
                    }
                    ?>
                </tr>
            </tbody>
        </table>
        
        <div class="stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($answered_dates); ?></div>
                <div class="stat-label">이번 달 작성</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo round((count($answered_dates) / $last_day) * 100); ?>%</div>
                <div class="stat-label">완성도</div>
            </div>
        </div>
    </div>
    
    <script>
    </script>
</body>
</html>