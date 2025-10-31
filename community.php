<?php
include 'config.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ---------------- 글 작성 ----------------
    if ($_POST['action'] === 'write') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title === '' || $content === '') {
            $message = '<div class="message error">제목과 내용을 모두 입력해주세요.</div>';
        } else {
            // 중복 체크
            $stmt = $conn->prepare("SELECT COUNT(*) FROM community WHERE title=? AND content=?");
            $stmt->bind_param("ss", $title, $content);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                $message = '<div class="message error">이미 동일한 질문이 존재합니다.</div>';
            } else {
                $stmt = $conn->prepare("INSERT INTO community (user_id, title, content) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user_id, $title, $content);
                if ($stmt->execute()) {
                    $message = '<div class="message success">✓ 게시글이 등록되었습니다!</div>';
                } else {
                    $message = '<div class="message error">게시글 등록 중 오류가 발생했습니다.</div>';
                    error_log("게시글 등록 실패: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }

    // ---------------- 댓글 작성 ----------------
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
                error_log("댓글 등록 실패: " . $stmt->error);
            }
            $stmt->close();
        }
    }

    // ---------------- 게시글 삭제 ----------------
    if ($_POST['action'] === 'delete_post') {
        $post_id = intval($_POST['post_id']);
        // 작성자 확인 후 삭제
        $stmt = $conn->prepare("DELETE FROM community WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // 댓글도 함께 삭제
        $stmt = $conn->prepare("DELETE FROM comments WHERE post_id=?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();

        $message = '<div class="message success">게시글이 삭제되었습니다.</div>';
    }

    // ---------------- 댓글 삭제 ----------------
    if ($_POST['action'] === 'delete_comment') {
        $comment_id = intval($_POST['comment_id']);
        $stmt = $conn->prepare("DELETE FROM comments WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $comment_id, $user_id);
        $stmt->execute();
        $stmt->close();

        $message = '<div class="message success">댓글이 삭제되었습니다.</div>';
    }
}

// ---------------- 게시글 + 댓글 불러오기 ----------------
$result = $conn->query("
    SELECT c.id, c.title, c.content, c.created_at, u.username, c.user_id
    FROM community c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.created_at DESC
");
$posts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

foreach ($posts as &$post) {
    $stmt = $conn->prepare("
        SELECT cm.id, cm.comment, cm.created_at, u.username, cm.user_id 
        FROM comments cm 
        JOIN users u ON cm.user_id = u.id 
        WHERE cm.post_id = ? 
        ORDER BY cm.created_at ASC
    ");
    $stmt->bind_param("i", $post['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $post['comments'] = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>커뮤니티</title>
<style>
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
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg-light); color: var(--text-light); min-height: 100vh; padding: 20px; transition: 0.3s; }
body.dark-mode { background: var(--bg-dark); color: var(--text-dark); }

.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; max-width: 900px; margin: 0 auto 40px auto; }
.header h1 { font-size: 28px; font-weight: 700; }
.header-right { display: flex; gap: 12px; align-items: center; }
.header-right .username { font-size: 14px; font-weight: 500; opacity: 0.7; }
.logout-btn { text-decoration: none; color: #fff; background: linear-gradient(135deg, #5b7cfa 0%, #667eea 100%); padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: 14px; transition: all 0.3s; border: none; cursor: pointer; }
.logout-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(91,124,250,0.3); }

.nav-links { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; max-width: 900px; margin: 0 auto 40px auto; }
.nav-links a { padding: 12px 20px; background: var(--card-light); color: var(--primary); text-decoration: none; border-radius: 12px; font-size: 14px; font-weight: 600; border: 2px solid transparent; transition: all 0.3s; }
.nav-links a.active { background: var(--primary); color: #fff; }
.nav-links a:hover { border-color: var(--primary); background: var(--primary); color: #fff; }
body.dark-mode .nav-links a { background: var(--card-dark); }

.container { max-width: 900px; margin: 0 auto; }

.message { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; }
.message.error { background-color: #ffe5e5; color: #d60000; }
.message.success { background-color: #e5ffe5; color: #008000; }

.post-form, .post { background: var(--card-light); border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: all 0.3s; }
.post-form input[type="text"], .post-form textarea { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #ccc; font-size: 1rem; }
.post-form input[type="submit"] { padding: 10px 20px; border-radius: 8px; border: none; background: var(--primary); color: #fff; cursor: pointer; font-weight: 600; transition: all 0.3s; }
.post-form input[type="submit"]:hover { background: var(--primary-dark); }

.post h2 { margin: 0 0 10px; font-weight: 700; }
.post p { line-height: 1.6; color: #555; }
.post small { display: block; margin-top: 10px; color: #999; }

.comments { margin-top: 15px; padding-left: 15px; border-left: 3px solid #f0f0f0; }
.comment { margin-bottom: 10px; font-size: 0.95rem; }
.comment strong { color: #444; }

.comment-form { margin-top: 10px; display: flex; gap: 10px; }
.comment-form input[type="text"] { flex: 1; padding: 8px; border-radius: 8px; border: 1px solid #ccc; }
.comment-form input[type="submit"] { padding: 8px 15px; border-radius: 8px; border: none; background: var(--primary); color: #fff; cursor: pointer; transition: all 0.3s; }
.comment-form input[type="submit"]:hover { background: var(--primary-dark); }

.delete-btn { background:red;color:#fff;padding:4px 8px;border:none;border-radius:4px;cursor:pointer;margin-left:10px;font-size:0.8rem; }

body.dark-mode .post, body.dark-mode .post-form, body.dark-mode .comments { background: var(--card-dark); }
body.dark-mode .comment { color: var(--text-dark); }

@media (max-width: 600px) {
    .post, .post-form { padding: 15px; }
    .comment-form input[type="text"] { font-size: 14px; }
    .nav-links a { flex: 1; text-align: center; }
}
</style>
</head>
<body>
<div class="header">
    <h1>커뮤니티</h1>
    <div class="header-right">
        <span class="username"><?=htmlspecialchars($username)?></span>
        <a href="logout.php" class="logout-btn">로그아웃</a>
    </div>
</div>

<!-- 네비게이션 -->
<div class="nav-links">
    <a href="main.php">오늘의 질문</a>
    <a href="calendar.php">캘린더</a>
    <a href="insight.php">인사이트</a>
    <a href="community.php" class="active">커뮤니티</a>
</div>

<div class="container">
<?= $message ?>

<!-- 게시글 작성 폼 -->
<div class="post-form">
<form method="POST">
    <input type="hidden" name="action" value="write">
    <input type="text" name="title" placeholder="제목" required>
    <textarea name="content" rows="4" placeholder="내용" required></textarea>
    <input type="submit" value="등록하기">
</form>
</div>

<!-- 게시글 목록 -->
<?php foreach ($posts as $post): ?>
<div class="post">
    <h2><?=htmlspecialchars($post['title'])?>
        <?php if ($post['user_id'] == $user_id): ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="post_id" value="<?=$post['id']?>">
            <input type="submit" class="delete-btn" value="삭제">
        </form>
        <?php endif; ?>
    </h2>
    <p><?=nl2br(htmlspecialchars($post['content']))?></p>
    <small>작성자: <?=htmlspecialchars($post['username'])?> | <?=$post['created_at']?></small>

    <div class="comments">
        <?php foreach ($post['comments'] as $comment): ?>
            <div class="comment">
                <strong><?=htmlspecialchars($comment['username'])?>:</strong> <?=nl2br(htmlspecialchars($comment['comment']))?>
                <?php if ($comment['user_id'] == $user_id): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete_comment">
                    <input type="hidden" name="comment_id" value="<?=$comment['id']?>">
                    <input type="submit" class="delete-btn" value="삭제">
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <form method="POST" class="comment-form">
            <input type="hidden" name="action" value="comment">
            <input type="hidden" name="post_id" value="<?=$post['id']?>">
            <input type="text" name="comment_content" placeholder="댓글 작성" required>
            <input type="submit" value="등록">
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
