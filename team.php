<?php
require_once __DIR__ . '/config/db_connect.php';

$team_id = $_GET['team_id'] ?? null;
$event_id = $_GET['event_id'] ?? null;
if (!$team_id || !$event_id) {
    header('Location: index.php');
    exit;
}

// チーム情報取得
$stmt = $pdo->prepare('SELECT * FROM teams WHERE id = ?');
$stmt->execute([$team_id]);
$team = $stmt->fetch();
if (!$team) {
    echo 'チームが見つかりません';
    exit;
}

// イベント情報取得
$stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) {
    echo 'イベントが見つかりません';
    exit;
}

// 領収書追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_receipt'])) {
    $amount = $_POST['amount'] ?? 0;
    $person_name = $_POST['person_name'] ?? '';
    $image_path = '';
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'assets/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $filename = date('YmdHis') . '_' . basename($_FILES['receipt_image']['name']);
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target)) {
            $image_path = $target;
        }
    }
    if ($amount && $person_name && $image_path) {
        $stmt = $pdo->prepare('INSERT INTO receipts (team_id, person_name, amount, image_path) VALUES (?, ?, ?, ?)');
        $stmt->execute([$team_id, $person_name, $amount, $image_path]);
        // Slack通知処理
        require_once __DIR__ . '/slack_post.php';
        post_receipt_to_slack($event, $team, $amount, $person_name);
        header('Location: team.php?team_id=' . $team_id . '&event_id=' . $event_id);
        exit;
    }
}

// 領収書編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_receipt'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $person_name = $_POST['person_name'] ?? '';
    $image_path = $_POST['old_image_path'] ?? '';
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'assets/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $filename = date('YmdHis') . '_' . basename($_FILES['receipt_image']['name']);
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target)) {
            $image_path = $target;
        }
    }
    if ($edit_id && $amount && $person_name) {
        $stmt = $pdo->prepare('UPDATE receipts SET person_name=?, amount=?, image_path=? WHERE id=?');
        $stmt->execute([$person_name, $amount, $image_path, $edit_id]);
        header('Location: team.php?team_id=' . $team_id . '&event_id=' . $event_id);
        exit;
    }
}

// 領収書削除処理
if (isset($_GET['delete_receipt'])) {
    $delete_id = $_GET['delete_receipt'];
    $stmt = $pdo->prepare('DELETE FROM receipts WHERE id=?');
    $stmt->execute([$delete_id]);
    header('Location: team.php?team_id=' . $team_id . '&event_id=' . $event_id);
    exit;
}

// 領収書一覧取得
$stmt = $pdo->prepare('SELECT * FROM receipts WHERE team_id = ? ORDER BY id DESC');
$stmt->execute([$team_id]);
$receipts = $stmt->fetchAll();

// チームの領収書総額
$stmt = $pdo->prepare('SELECT SUM(amount) as total FROM receipts WHERE team_id = ?');
$stmt->execute([$team_id]);
$used_total = $stmt->fetchColumn() ?: 0;
$budget_left = $team['budget'] - $used_total;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チーム詳細 - <?= htmlspecialchars($team['name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <h1 class="title">👥 <?= htmlspecialchars($team['name']) ?></h1>
            
            <div class="breadcrumb">
                <a href="event.php?event_id=<?= $event_id ?>">← <?= htmlspecialchars($event['name']) ?>に戻る</a>
            </div>

            <div class="info-cards">
                <div class="info-card budget">
                    <h3>チーム予算</h3>
                    <div class="amount">¥<?= number_format($team['budget']) ?></div>
                </div>
                <div class="info-card used">
                    <h3>使用総額</h3>
                    <div class="amount">¥<?= number_format($used_total) ?></div>
                </div>
                <div class="info-card remaining">
                    <h3>予算残り</h3>
                    <div class="amount">¥<?= number_format($budget_left) ?></div>
                </div>
            </div>

            <h2 class="subtitle">🧾 領収書一覧</h2>
            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>個人名</th>
                            <th>金額</th>
                            <th>画像</th>
                            <th>登録日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($receipt['person_name']) ?></strong></td>
                            <td><strong>¥<?= number_format($receipt['amount']) ?></strong></td>
                            <td>
                                <?php if ($receipt['image_path']): ?>
                                    <a href="<?= htmlspecialchars($receipt['image_path']) ?>" target="_blank" class="img-link">📷 画像を見る</a>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(date('Y/m/d H:i', strtotime($receipt['created_at']))) ?></td>
                            <td>
                                <a href="?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>&edit_receipt=<?= $receipt['id'] ?>" class="btn btn-edit btn-sm">編集</a>
                                <a href="?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>&delete_receipt=<?= $receipt['id'] ?>" 
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
            if (isset($_GET['edit_receipt'])) {
                $edit_id = $_GET['edit_receipt'];
                $stmt = $pdo->prepare('SELECT * FROM receipts WHERE id = ?');
                $stmt->execute([$edit_id]);
                $edit_receipt = $stmt->fetch();
                if ($edit_receipt): ?>
                <h2 class="subtitle">✏️ 領収書編集</h2>
                <form method="post" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="edit_id" value="<?= $edit_receipt['id'] ?>">
                    <input type="hidden" name="old_image_path" value="<?= htmlspecialchars($edit_receipt['image_path']) ?>">
                    <div class="form-group">
                        <label>個人名</label>
                        <input type="text" name="person_name" value="<?= htmlspecialchars($edit_receipt['person_name']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>金額（円）</label>
                        <input type="number" name="amount" value="<?= htmlspecialchars($edit_receipt['amount']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>領収書画像（変更する場合のみ選択）</label>
                        <input type="file" name="receipt_image" accept="image/*" class="input">
                        <?php if ($edit_receipt['image_path']): ?>
                            <p><a href="<?= htmlspecialchars($edit_receipt['image_path']) ?>" target="_blank" class="img-link">📷 現在の画像を確認</a></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <a href="team.php?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>" class="btn btn-outline">キャンセル</a>
                        <button type="submit" name="update_receipt" class="btn btn-primary">更新</button>
                    </div>
                </form>
                <?php endif;
            } else { ?>
                <h2 class="subtitle">➕ 新しい領収書追加</h2>
                <form method="post" enctype="multipart/form-data" class="form">
                    <div class="form-group">
                        <label>個人名</label>
                        <input type="text" name="person_name" required class="input" placeholder="例：田中太郎">
                    </div>
                    <div class="form-group">
                        <label>金額（円）</label>
                        <input type="number" name="amount" required class="input" placeholder="1000">
                    </div>
                    <div class="form-group">
                        <label>領収書画像</label>
                        <input type="file" name="receipt_image" accept="image/*" required class="input">
                        <small style="color: #718096;">JPG, PNG形式の画像をアップロードしてください</small>
                    </div>
                    <div class="text-right">
                        <button type="submit" name="add_receipt" class="btn btn-success">追加してSlackに通知</button>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
</body>
</html>
