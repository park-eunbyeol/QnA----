<?php
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: main.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    
    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = "모든 항목을 입력하세요.";
    } elseif (strlen($username) < 3) {
        $error = "사용자명은 3글자 이상이어야 합니다.";
    } elseif (strlen($password) < 6) {
        $error = "비밀번호는 6글자 이상이어야 합니다.";
    } elseif ($password !== $password_confirm) {
        $error = "비밀번호가 일치하지 않습니다.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "이미 존재하는 사용자명 또는 이메일입니다.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = "회원가입이 완료되었습니다. 로그인하세요.";
            } else {
                $error = "회원가입 중 오류가 발생했습니다.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q&A 다이어리 - 회원가입</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #5b7cfa;
            box-shadow: 0 0 0 2px rgba(91,124,250,0.1);
        }
        button {
            width: 100%;
            padding: 10px;
            background: #5b7cfa;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background: #4c63dd;
        }
        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }
        .error {
            background: #ffe0e0;
            color: #d32f2f;
            border: 1px solid #ffb3b3;
        }
        .success {
            background: #e0ffe0;
            color: #2d7a2d;
            border: 1px solid #b3ffb3;
        }
        .link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .link a {
            color: #5b7cfa;
            text-decoration: none;
            font-weight: 600;
        }
        .link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📖 회원가입</h1>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">사용자명</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">이메일</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">비밀번호 확인</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button type="submit">회원가입</button>
        </form>
        
        <div class="link">
            이미 계정이 있으신가요? <a href="index.php">로그인</a>
        </div>
    </div>
</body>
</html>