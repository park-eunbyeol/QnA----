<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$today = date('Y-m-d');

// 오늘의 질문 가져오기
$question_index = (int)date('z') % 12;
$stmt = $conn->prepare("SELECT id, question, category FROM questions LIMIT 1 OFFSET ?");
$stmt->bind_param("i", $question_index);
$stmt->execute();
$result = $stmt->get_result();
$question_data = $result->fetch_assoc();
$stmt->close();

// 오늘의 답변 확인
$stmt = $conn->prepare("SELECT id, answer FROM answers WHERE user_id = ? AND answer_date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$today_answer = $result->fetch_assoc();
$stmt->close();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 삭제 요청
    if (isset($_POST['delete']) && $_POST['delete'] == '1') {
        $stmt = $conn->prepare("DELETE FROM answers WHERE user_id = ? AND answer_date = ?");
        $stmt->bind_param("is", $user_id, $today);
        
        if ($stmt->execute()) {
            $message = '<div class="message success">✓ 답변이 삭제되었습니다!</div>';
            $today_answer = null;
        } else {
            $message = '<div class="message error">삭제 중 오류가 발생했습니다.</div>';
        }
        $stmt->close();
    } else {
        // 저장 요청
        $answer_text = trim($_POST['answer'] ?? '');
        
        if (empty($answer_text)) {
            $message = '<div class="message error">답변을 입력하세요.</div>';
        } else {
            if ($today_answer) {
                $stmt = $conn->prepare("UPDATE answers SET answer = ? WHERE user_id = ? AND answer_date = ?");
                $stmt->bind_param("sis", $answer_text, $user_id, $today);
            } else {
                $stmt = $conn->prepare("INSERT INTO answers (user_id, question_id, answer, category, answer_date) VALUES (?, ?, ?, ?, ?)");
                $category = $question_data['category'];
                $stmt->bind_param("iisss", $user_id, $question_data['id'], $answer_text, $category, $today);
            }
            
            if ($stmt->execute()) {
                $message = '<div class="message success">✓ 저장되었습니다!</div>';
                $today_answer = null;
            } else {
                $message = '<div class="message error">저장 중 오류가 발생했습니다.</div>';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q&A 다이어리 - 메인</title>
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
            --accent-blue: #5b7cfa;
            --success: #10b981;
            --error: #ef4444;
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
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-light);
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
            max-width: 700px;
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
            cursor: pointer;
        }
        
        body.dark-mode .nav-links a {
            background: var(--card-dark);
            color: var(--primary);
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
            background: var(--card-light);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            transition: all 0.3s;
        }
        
        body.dark-mode .container {
            background: var(--card-dark);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .date-info {
            color: var(--text-light);
            opacity: 0.6;
            font-size: 14px;
            margin-bottom: 24px;
            font-weight: 500;
        }
        
        body.dark-mode .date-info {
            color: var(--text-dark);
        }
        
        .question-box {
            background: linear-gradient(135deg, rgba(91, 124, 250, 0.08) 0%, rgba(102, 126, 234, 0.08) 100%);
            padding: 28px;
            border-radius: 14px;
            margin-bottom: 32px;
            border-left: 5px solid var(--primary);
            transition: all 0.3s;
        }
        
        body.dark-mode .question-box {
            background: linear-gradient(135deg, rgba(91, 124, 250, 0.15) 0%, rgba(102, 126, 234, 0.15) 100%);
        }
        
        .question-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(91, 124, 250, 0.15);
        }
        
        .question-label {
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .question-text {
            color: var(--text-light);
            font-size: 22px;
            font-weight: 600;
            line-height: 1.7;
        }
        
        body.dark-mode .question-text {
            color: var(--text-dark);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        label {
            display: block;
            margin-bottom: 12px;
            color: var(--text-light);
            font-weight: 600;
            font-size: 15px;
        }
        
        body.dark-mode label {
            color: var(--text-dark);
        }
        
        textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            font-family: inherit;
            font-size: 15px;
            resize: vertical;
            min-height: 160px;
            background: var(--card-light);
            color: var(--text-light);
            transition: all 0.3s;
        }
        
        body.dark-mode textarea {
            background: rgba(0, 0, 0, 0.2);
            border-color: var(--border-dark);
            color: var(--text-dark);
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(91, 124, 250, 0.1);
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        button {
            padding: 14px 28px;
            background: linear-gradient(135deg, #5b7cfa 0%, #667eea 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            min-width: 150px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(91, 124, 250, 0.3);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .message {
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            animation: slideDown 0.3s ease-out;
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
        
        .error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .saved-mark {
            color: var(--success);
            font-size: 13px;
            margin-top: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 24px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .question-text {
                font-size: 18px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            button {
                min-width: auto;
            }
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
        <a href="main.php" class="active">오늘의 질문</a>
        <a href="calendar.php">캘린더</a>
        <a href="insight.php">인사이트</a>
        <a href="community.php" class="active">커뮤니티</a>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <div class="date-info">
            📅 <?php echo date('Y년 m월 d일 (l)', strtotime($today)); ?>
        </div>
        
        <div class="question-box">
            <div class="question-label"><?php echo htmlspecialchars($question_data['category']); ?></div>
            <div class="question-text"><?php echo htmlspecialchars($question_data['question']); ?></div>
        </div>
        
        <form method="POST" id="answerForm">
            <div class="form-group">
                <label for="answer">나의 답변</label>
                <textarea id="answer" name="answer" placeholder="당신의 생각과 느낌을 자유롭게 적어보세요..." required><?php echo htmlspecialchars($today_answer['answer'] ?? ''); ?></textarea>
                <?php if ($today_answer): ?>
                    <div class="saved-mark">✓ 저장됨</div>
                <?php endif; ?>
            </div>
            <div class="button-group">
                <button type="submit" name="save" value="1" onclick="setTimeout(function(){ document.getElementById('answer').value=''; }, 300);">💾 저장하기</button>
                <button type="submit" name="delete" value="1" style="background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);" onclick="return confirm('정말 삭제하시겠습니까?');">🗑️ 삭제</button>
            </div>
        </form>
    </div>
    

</body>
</html>