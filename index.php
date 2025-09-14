<?php
require_once __DIR__ . '/config/db_connect.php';

// イベント追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $name = $_POST['name'] ?? '';
    $budget = $_POST['budget'] ?? 0;
    $slack_url = $_POST['slack_channel_url'] ?? '';
    if ($name && $budget && $slack_url) {
        $stmt = $pdo->prepare('INSERT INTO events (name, budget, slack_channel_url) VALUES (?, ?, ?)');
        $stmt->execute([$name, $budget, $slack_url]);
        header('Location: index.php');
        exit;
    }
}

// イベント編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $budget = $_POST['budget'] ?? 0;
    $slack_url = $_POST['slack_channel_url'] ?? '';
    if ($edit_id && $name && $budget && $slack_url) {
        $stmt = $pdo->prepare('UPDATE events SET name=?, budget=?, slack_channel_url=? WHERE id=?');
        $stmt->execute([$name, $budget, $slack_url, $edit_id]);
        header('Location: index.php');
        exit;
    }
}

// イベント削除処理
if (isset($_GET['delete_event'])) {
    $delete_id = $_GET['delete_event'];
    $stmt = $pdo->prepare('DELETE FROM events WHERE id=?');
    $stmt->execute([$delete_id]);
    header('Location: index.php');
    exit;
}

// イベント一覧取得
$events = $pdo->query('SELECT * FROM events ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>イベント管理システム</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <h1 class="title">📅 イベント管理システム</h1>
            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>イベント名</th>
                            <th>予算</th>
                            <th>Slack URL</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($event['name']) ?></strong></td>
                            <td>¥<?= number_format($event['budget']) ?></td>
                            <td><span class="img-link">設定済み</span></td>
                            <td>
                                <a href="event.php?event_id=<?= $event['id'] ?>" class="btn btn-primary btn-sm">選択</a>
                                <a href="?edit_event=<?= $event['id'] ?>" class="btn btn-edit btn-sm">編集</a>
                                <a href="?delete_event=<?= $event['id'] ?>" 
                                   onclick="return confirm('本当に削除しますか？');" 
                                   class="btn btn-delete btn-sm">削除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // 編集フォーム表示
            if (isset($_GET['edit_event'])) {
                $edit_id = $_GET['edit_event'];
                $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
                $stmt->execute([$edit_id]);
                $edit_event = $stmt->fetch();
                if ($edit_event): ?>
                <h2 class="subtitle">✏️ イベント編集</h2>
                <form method="post" class="form">
                    <input type="hidden" name="edit_id" value="<?= $edit_event['id'] ?>">
                    <div class="form-group">
                        <label>イベント名</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($edit_event['name']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>予算</label>
                        <input type="number" name="budget" value="<?= htmlspecialchars($edit_event['budget']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>Slack Webhook URL</label>
                        <input type="url" name="slack_channel_url" value="<?= htmlspecialchars($edit_event['slack_channel_url']) ?>" required class="input">
                    </div>
                    <div class="text-right">
                        <a href="index.php" class="btn btn-outline">キャンセル</a>
                        <button type="submit" name="update_event" class="btn btn-primary">更新</button>
                    </div>
                </form>
                <?php endif;
            } else { ?>
                <h2 class="subtitle">➕ 新しいイベント追加</h2>
                <form method="post" class="form">
                    <div class="form-group">
                        <label>イベント名</label>
                        <input type="text" name="name" required class="input" placeholder="例：春の学園祭">
                    </div>
                    <div class="form-group">
                        <label>予算</label>
                        <input type="number" name="budget" required class="input" placeholder="100000">
                    </div>
                    <div class="form-group">
                        <label>Slack Webhook URL</label>
                        <input type="url" name="slack_channel_url" required class="input" placeholder="https://hooks.slack.com/...">
                    </div>
                    <div class="text-right">
                        <button type="submit" name="add_event" class="btn btn-success">追加</button>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
</body>
</html>
