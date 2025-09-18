<?php
require_once __DIR__ . '/config/db_connect.php';

$event_id = $_GET['event_id'] ?? null;
if (!$event_id) {
    header('Location: index.php');
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

// チーム追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_team'])) {
    $team_name = $_POST['team_name'] ?? '';
    $team_budget = $_POST['team_budget'] ?? 0;
    if ($team_name && $team_budget) {
        $stmt = $pdo->prepare('INSERT INTO teams (event_id, name, budget) VALUES (?, ?, ?)');
        $stmt->execute([$event_id, $team_name, $team_budget]);
        header('Location: event.php?event_id=' . $event_id);
        exit;
    }
}

// チーム編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_team'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $team_name = $_POST['team_name'] ?? '';
    $team_budget = $_POST['team_budget'] ?? 0;
    if ($edit_id && $team_name && $team_budget) {
        $stmt = $pdo->prepare('UPDATE teams SET name=?, budget=? WHERE id=?');
        $stmt->execute([$team_name, $team_budget, $edit_id]);
        header('Location: event.php?event_id=' . $event_id);
        exit;
    }
}

// チーム削除処理
if (isset($_GET['delete_team'])) {
    $delete_id = $_GET['delete_team'];
    $stmt = $pdo->prepare('DELETE FROM teams WHERE id=?');
    $stmt->execute([$delete_id]);
    header('Location: event.php?event_id=' . $event_id);
    exit;
}

// チーム一覧取得
$stmt = $pdo->prepare('SELECT * FROM teams WHERE event_id = ? ORDER BY id DESC');
$stmt->execute([$event_id]);
$teams = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>イベント詳細 - <?= htmlspecialchars($event['name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/KFロゴ.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/KFロゴ.svg">
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#4A90E2">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <h1 class="title">🎪 <?= htmlspecialchars($event['name']) ?></h1>
            
            <div class="breadcrumb">
                <a href="index.php">← イベント一覧へ戻る</a>
            </div>

            <div class="card">
                <div class="info-cards">
                    <div class="info-card budget">
                        <h3>総予算</h3>
                        <div class="amount">¥<?= number_format($event['budget']) ?></div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="budget_overview.php?event_id=<?= $event_id ?>" class="btn btn-info">📊 予算管理概要を見る</a>
                </div>
            </div>

            <h2 class="subtitle">👥 チーム一覧</h2>
            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>チーム名</th>
                            <th>予算</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($team['name']) ?></strong></td>
                            <td>¥<?= number_format($team['budget']) ?></td>
                            <td>
                                <a href="team.php?team_id=<?= $team['id'] ?>&event_id=<?= $event_id ?>" class="btn btn-primary btn-sm">選択</a>
                                <a href="?event_id=<?= $event_id ?>&edit_team=<?= $team['id'] ?>" class="btn btn-edit btn-sm">編集</a>
                                <a href="?event_id=<?= $event_id ?>&delete_team=<?= $team['id'] ?>" 
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
            if (isset($_GET['edit_team'])) {
                $edit_id = $_GET['edit_team'];
                $stmt = $pdo->prepare('SELECT * FROM teams WHERE id = ?');
                $stmt->execute([$edit_id]);
                $edit_team = $stmt->fetch();
                if ($edit_team): ?>
                <h2 class="subtitle">✏️ チーム編集</h2>
                <form method="post" class="form">
                    <input type="hidden" name="edit_id" value="<?= $edit_team['id'] ?>">
                    <div class="form-group">
                        <label>チーム名</label>
                        <input type="text" name="team_name" value="<?= htmlspecialchars($edit_team['name']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>予算</label>
                        <input type="number" name="team_budget" value="<?= htmlspecialchars($edit_team['budget']) ?>" required class="input">
                    </div>
                    <div class="text-right">
                        <a href="event.php?event_id=<?= $event_id ?>" class="btn btn-outline">キャンセル</a>
                        <button type="submit" name="update_team" class="btn btn-primary">更新</button>
                    </div>
                </form>
                <?php endif;
            } else { ?>
                <h2 class="subtitle">➕ 新しいチーム追加</h2>
                <form method="post" class="form">
                    <div class="form-group">
                        <label>チーム名</label>
                        <input type="text" name="team_name" required class="input" placeholder="例：実行委員会">
                    </div>
                    <div class="form-group">
                        <label>予算</label>
                        <input type="number" name="team_budget" required class="input" placeholder="50000">
                    </div>
                    <div class="text-right">
                        <button type="submit" name="add_team" class="btn btn-success">追加</button>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
</body>
</html>
