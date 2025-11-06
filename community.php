<?php
include 'config.php';

// 문자셋 명시적 설정
$conn->set_charset("utf8mb4");

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$message = '';

// 현재 페이지 번호
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, $page);

// 게시글 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_post') {
    $post_id = intval($_POST['post_id']);
    
    // 본인 게시글인지 확인
    $stmt = $conn->prepare("SELECT user_id FROM community WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    
    if ($post && $post['user_id'] == $user_id) {
        $stmt = $conn->prepare("DELETE FROM community WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        if ($stmt->execute()) {
            $message = '<div class="message success">✓ 게시글이 삭제되었습니다!</div>';
        } else {
            $message = '<div class="message error">게시글 삭제 중 오류가 발생했습니다.</div>';
        }
    } else {
        $message = '<div class="message error">본인의 게시글만 삭제할 수 있습니다.</div>';
    }
    $stmt->close();
}

// 댓글 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
    $comment_id = intval($_POST['comment_id']);
    
    // 본인 댓글인지 확인
    $stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comment = $result->fetch_assoc();
    
    if ($comment && $comment['user_id'] == $user_id) {
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        if ($stmt->execute()) {
            $message = '<div class="message success">✓ 댓글이 삭제되었습니다!</div>';
        } else {
            $message = '<div class="message error">댓글 삭제 중 오류가 발생했습니다.</div>';
        }
    } else {
        $message = '<div class="message error">본인의 댓글만 삭제할 수 있습니다.</div>';
    }
    $stmt->close();
}

// 게시글 작성 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($_POST['action'] === 'write') {
        if ($title === '' || $content === '') {
            $message = '<div class="message error">제목과 내용을 모두 입력해주세요.</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO community (user_id, title, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $title, $content);
            if ($stmt->execute()) {
                $message = '<div class="message success">✓ 게시글이 등록되었습니다!</div>';
            } else {
                $message = '<div class="message error">게시글 등록 중 오류가 발생했습니다.</div>';
            }
            $stmt->close();
        }
    }
    
    // 댓글 작성 처리
    if ($_POST['action'] === 'comment') {
        $post_id = intval($_POST['post_id']);
        $comment_content = trim($_POST['comment_content'] ?? '');
        
        if ($comment_content === '') {
            $message = '<div class="message error">댓글 내용을 입력해주세요.</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $post_id, $user_id, $comment_content);
            if ($stmt->execute()) {
                $message = '<div class="message success">✓ 댓글이 등록되었습니다!</div>';
            } else {
                $message = '<div class="message error">댓글 등록 중 오류가 발생했습니다.</div>';
            }
            $stmt->close();
        }
    }
}

// 전체 게시글 수 가져오기
$total_result = $conn->query("SELECT COUNT(*) as total FROM community");
$total_row = $total_result->fetch_assoc();
$total_posts = $total_row['total'];

// 현재 페이지의 게시글 가져오기 (1개씩)
$offset = ($page - 1);
$result = $conn->query("
    SELECT c.id, c.title, c.content, c.created_at, c.user_id, u.username 
    FROM community c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.created_at DESC
    LIMIT 1 OFFSET $offset
");
$posts = $result->fetch_all(MYSQLI_ASSOC);

// 각 게시글의 댓글 가져오기
foreach ($posts as &$post) {
    $stmt = $conn->prepare("
        SELECT cm.id, cm.comment, cm.created_at, cm.user_id, u.username 
        FROM comments cm 
        JOIN users u ON cm.user_id = u.id 
        WHERE cm.post_id = ? 
        ORDER BY cm.created_at ASC
    ");
    $stmt->bind_param("i", $post['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $post['comments'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>커뮤니티 - Q&A 다이어리</title>
    <style>
        /* 기존 스타일 그대로 유지 */
        * { margin: 0; padding: 0; box-sizing: border-box; }

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
            --error: #ef4444;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-light);
            padding: 20px;
            transition: background 0.3s, color 0.3s;
        }

        .header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 40px; max-width: 700px; margin: 0 auto;
        }

        h1 { font-size: 28px; font-weight: 700; }

        .logout-btn {
            text-decoration: none; color: white;
            background: linear-gradient(135deg, #5b7cfa, #667eea);
            padding: 10px 18px; border-radius: 10px;
            font-weight: 600; font-size: 14px;
        }

        .nav-links {
            display: flex; gap: 10px; justify-content: center;
            margin: 30px auto; max-width: 700px;
        }

        .nav-links a {
            padding: 12px 20px; border-radius: 12px;
            text-decoration: none; font-weight: 600; font-size: 14px;
            background: var(--card-light); color: var(--primary);
            transition: all 0.3s;
        }

        .nav-links a.active, .nav-links a:hover {
            background: var(--primary); color: white;
        }

        .container {
            background: var(--card-light);
            padding: 40px; border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 700px; margin: 0 auto;
        }

        .message {
            padding: 14px; border-radius: 12px;
            margin-bottom: 24px; font-size: 14px; font-weight: 600;
        }
        .error { background: rgba(239,68,68,0.1); color: #dc2626; }
        .success { background: rgba(16,185,129,0.1); color: #059669; }

        .form-group { margin-bottom: 20px; }
        input, textarea {
            width: 100%; padding: 14px; border-radius: 10px;
            border: 2px solid var(--border-light);
            font-family: inherit; font-size: 15px;
        }

        textarea { min-height: 140px; resize: vertical; }

        button {
            padding: 12px 24px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #5b7cfa, #667eea);
            color: white; font-weight: 700; cursor: pointer;
            transition: all 0.3s;
        }

        button:hover { transform: translateY(-2px); }

        .post {
            background: #f9f9ff; border-radius: 12px;
            padding: 20px; margin-bottom: 20px;
            border-left: 4px solid var(--primary);
            transition: 0.3s;
            position: relative;
        }

        .post:hover { transform: translateY(-2px); }

        .post-title { font-size: 18px; font-weight: 700; color: var(--primary); margin-bottom: 8px; }
        .post-meta { font-size: 13px; color: gray; margin-bottom: 12px; }
        .post-content { font-size: 15px; line-height: 1.6; color: #444; white-space: pre-wrap; }

        /* 삭제 버튼 스타일 */
        .delete-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .delete-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }

        /* 댓글 스타일 */
        .comments-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .comment {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid #e0e0e0;
            position: relative;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .comment-meta {
            font-size: 12px;
            color: #888;
            font-weight: 600;
        }

        .comment-content {
            font-size: 14px;
            line-height: 1.5;
            color: #555;
        }

        .comment-form {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .comment-form input {
            flex: 1;
            padding: 10px;
            font-size: 14px;
        }

        .comment-form button {
            padding: 10px 20px;
            font-size: 14px;
        }

        /* 페이징 스타일 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
        }

        .pagination a, .pagination span {
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .pagination a {
            background: var(--primary);
            color: white;
        }

        .pagination a:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .pagination a.disabled {
            background: #ccc;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination .page-info {
            color: #666;
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .container { padding: 24px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💬 커뮤니티</h1>
        <div class="user-menu">
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
            <a href="logout.php" class="logout-btn">로그아웃</a>
        </div>
    </div>

    <div class="nav-links">
        <a href="main.php">오늘의 질문</a>
        <a href="calendar.php">캘린더</a>
        <a href="insight.php">인사이트</a>
        <a href="community.php" class="active">커뮤니티</a>
    </div>

    <div class="container">
        <?php echo $message; ?>

        <h2 style="margin-bottom:20px;">✍️ 글 작성하기</h2>
        <form method="POST">
            <input type="hidden" name="action" value="write">
            <div class="form-group">
                <input type="text" name="title" placeholder="제목을 입력하세요" required>
            </div>
            <div class="form-group">
                <textarea name="content" placeholder="내용을 입력하세요" required></textarea>
            </div>
            <button type="submit">등록하기</button>
        </form>

        <hr style="margin: 30px 0; border: 1px solid #eee;">

        <h2 style="margin-bottom:20px;">📰 최신 글</h2>
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="post">
                    <div class="post-header">
                        <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                        <?php if ($post['user_id'] == $user_id): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <button type="submit" class="delete-btn">삭제</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="post-meta">
                        작성자: <?php echo htmlspecialchars($post['username']); ?> | 
                        <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?>
                    </div>
                    <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                    
                    <!-- 댓글 섹션 -->
                    <div class="comments-section">
                        <h4 style="font-size: 15px; margin-bottom: 12px; color: #666;">
                            💬 댓글 <?php echo count($post['comments']); ?>개
                        </h4>
                        
                        <?php if (count($post['comments']) > 0): ?>
                            <?php foreach ($post['comments'] as $comment): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <div class="comment-meta">
                                            <?php echo htmlspecialchars($comment['username']); ?> · 
                                            <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                                        </div>
                                        <?php if ($comment['user_id'] == $user_id): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('댓글을 삭제하시겠습니까?');">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                <button type="submit" class="delete-btn">삭제</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    <div class="comment-content">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- 댓글 작성 폼 -->
                        <form method="POST" class="comment-form">
                            <input type="hidden" name="action" value="comment">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <input type="text" name="comment_content" placeholder="댓글을 입력하세요..." required>
                            <button type="submit">댓글 달기</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- 페이징 네비게이션 -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">← 이전</a>
                <?php else: ?>
                    <a href="#" class="disabled">← 이전</a>
                <?php endif; ?>

                <span class="page-info"><?php echo $page; ?> / <?php echo max(1, $total_posts); ?></span>

                <?php if ($page < $total_posts): ?>
                    <a href="?page=<?php echo $page + 1; ?>">다음 →</a>
                <?php else: ?>
                    <a href="#" class="disabled">다음 →</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>아직 게시글이 없습니다. 첫 글을 작성해보세요!</p>
        <?php endif; ?>
    </div>
</body>
</html>