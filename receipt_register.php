<?php
require_once __DIR__ . '/config/db_connect.php';

function postToSlack($webhook_url, $message) {
    $payload = json_encode(["text" => $message], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// 領収書登録処理
if (isset($_POST['receipt_submit'])) {
    $team_id = $_POST['team_id'];
    $amount = $_POST['amount'];
    $image_path = '';
    // team_id存在チェック
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE id = ?');
    $stmt->execute([$team_id]);
    if ($stmt->fetchColumn() == 0) {
        echo '<div class="alert alert-danger">選択したチームが存在しません。再度選択してください。</div>';
    } else {
        if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $filename = date('YmdHis') . '_' . basename($_FILES['receipt_image']['name']);
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target)) {
                $image_path = 'uploads/' . $filename;
            }
        }
        $stmt = $pdo->prepare('INSERT INTO receipts (team_id, amount, image_path) VALUES (?, ?, ?)');
        $stmt->execute([$team_id, $amount, $image_path]);
        // ...existing code...
    }

    // Slack投稿処理
    // チーム・イベント情報取得
    $stmt = $pdo->prepare('SELECT t.name AS team_name, t.budget AS team_budget, e.name AS event_name, e.budget AS event_budget, e.slack_channel_url, t.id AS team_id, e.id AS event_id FROM teams t JOIN events e ON t.event_id = e.id WHERE t.id = ?');
    $stmt->execute([$team_id]);
    $info = $stmt->fetch();

    // チームの領収書総額
    $stmt = $pdo->prepare('SELECT SUM(amount) AS total FROM receipts WHERE team_id = ?');
    $stmt->execute([$team_id]);
    $team_total = $stmt->fetchColumn();

    // イベント全体の領収書総額
    $stmt = $pdo->prepare('SELECT SUM(r.amount) AS total FROM receipts r JOIN teams t ON r.team_id = t.id WHERE t.event_id = ?');
    $stmt->execute([$info['event_id']]);
    $event_total = $stmt->fetchColumn();

    // メッセージ作成
    $message = "【領収書登録】\n" .
        "イベント: {$info['event_name']} (予算: {$info['event_budget']}円)\n" .
        "チーム: {$info['team_name']} (予算: {$info['team_budget']}円)\n" .
        "今回登録: {$amount}円\n" .
        // チーム残り予算
        $team_remaining = $info['team_budget'] - $team_total;
        "チーム使用総額: {$team_total}円\n" .
        "イベント使用総額: {$event_total}円\n" .
        "画像: " . ($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/' . $image_path);

    postToSlack($info['slack_channel_url'], $message);
}

// イベント・チーム一覧取得
            "チーム残り予算: {$team_remaining}円\n" .
$events = $pdo->query('SELECT * FROM events')->fetchAll();
$teams = [];
if (isset($_GET['event_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM teams WHERE event_id = ?');
    $stmt->execute([$_GET['event_id']]);
    $teams = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>領収書登録</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="container py-4">
    <h2>領収書登録</h2>
    <form method="post" enctype="multipart/form-data" class="mb-4">
        <div class="mb-2">
            <label>イベント選択</label>
            <select name="event_id" class="form-select" onchange="location.href='?event_id='+this.value" required>
                <option value="">選択してください</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= htmlspecialchars($event['id']) ?>" <?= (isset($_GET['event_id']) && $_GET['event_id'] == $event['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($event['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label>チーム選択</label>
            <select name="team_id" class="form-select" required>
                <option value="">選択してください</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= htmlspecialchars($team['id']) ?>">
                        <?= htmlspecialchars($team['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label>領収書金額</label>
            <input type="number" name="amount" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>領収書画像</label>
            <input type="file" name="receipt_image" class="form-control" accept="image/*" required>
        </div>
        <button type="submit" name="receipt_submit" class="btn btn-primary">登録</button>
    </form>
</body>
</html>
