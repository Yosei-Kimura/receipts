<?php
require_once __DIR__ . '/config/db_connect.php';
session_start();
// CSRFトークン生成・検証関数
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) !== 64) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf_token($token) {
    // POST送信時にトークンが空の場合は失敗
    if (empty($token)) return false;
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// サーバ側バリデーション関数
function validate_event($name, $budget, $slack_url) {
    if (!is_string($name) || strlen($name) < 1 || strlen($name) > 255) return false;
    if (!is_numeric($budget) || $budget < 0) return false;
    if (!preg_match('/^https:\/\/hooks\.slack\.com\//', $slack_url)) return false;
    return true;
}
function validate_team($name, $budget) {
    if (!is_string($name) || strlen($name) < 1 || strlen($name) > 255) return false;
    if (!is_numeric($budget) || $budget < 0) return false;
    return true;
}

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        die('CSRFトークンが不正です');
    }
    // デバッグ: POSTデータ表示
    echo '<pre style="background:#eee;">POST:'; var_dump($_POST); echo '</pre>';
    // デバッグ: $pdo確認
    if (!isset($pdo)) {
        die('DB接続ができていません ($pdo未定義)');
    }
    // ...existing code...
    // チーム登録
    if (isset($_POST['team_submit'])) {
        $event_id = $_POST['event_id'];
        $name = $_POST['team_name'];
        $budget = $_POST['team_budget'];
        if (validate_team($name, $budget)) {
            $stmt = $pdo->prepare('INSERT INTO teams (event_id, name, budget) VALUES (?, ?, ?)');
            if (!$stmt->execute([$event_id, $name, $budget])) {
                echo '<div style="color:red">SQLエラー: ' . htmlspecialchars($stmt->errorInfo()[2]) . '</div>';
            } else {
                echo '<div style="color:green">チーム登録成功</div>';
            }
        } else {
            echo '<div style="color:red">バリデーションエラー</div>';
        }
    }
    // ...existing code...
}

// イベント一覧取得
$events = $pdo->query('SELECT * FROM events')->fetchAll();
// イベント選択後のチーム一覧取得
$selected_event_id = isset($_POST['selected_event_id']) ? $_POST['selected_event_id'] : '';
$teams = [];
if ($selected_event_id) {
    $stmt = $pdo->prepare('SELECT * FROM teams WHERE event_id = ?');
    $stmt->execute([$selected_event_id]);
    $teams = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>イベント・チーム登録</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <script>
        // PHPで生成したCSRFトークンをwindow.csrfTokenにセット
        window.csrfToken = "<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>";
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form').forEach(function(form) {
                // チーム一覧のイベント選択フォームは除外（onchangeでsubmitするため）
                if (form.querySelector('select[name="selected_event_id"]')) return;
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(form);
                    // 既存のcsrf_token hiddenがあっても、window.csrfTokenで上書き
                    formData.set('csrf_token', window.csrfToken);
                    // 削除ボタンはconfirm
                    if (form.querySelector('button[type="submit"][name$="delete_submit"]')) {
                        if (!confirm('本当に削除しますか？')) return;
                    }
                    fetch('', {
                        method: 'POST',
                        body: formData
                    }).then(function(res) {
                        if (res.ok) {
                            // サーバ側で新しいCSRFトークンが生成された場合、HTMLを再描画してwindow.csrfTokenを更新
                            location.reload();
                        }
                    });
                });
            });
        });
    </script>
    <h2>イベント登録</h2>
    <form method="post" class="mb-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-2">
            <label>イベント名</label>
            <input type="text" name="event_name" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>イベント予算</label>
            <input type="number" name="event_budget" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>Slack Webhook URL</label>
            <input type="text" name="slack_channel_url" class="form-control" required>
        </div>
        <button type="submit" name="event_submit" class="btn btn-primary">イベント登録</button>
    </form>

    <h3>イベント一覧</h3>
    <table class="table table-bordered mb-4">
        <thead><tr><th>名前</th><th>予算</th><th>Slack URL</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><?= htmlspecialchars($event['name']) ?></td>
                <td><?= htmlspecialchars($event['budget']) ?></td>
                <td><?= htmlspecialchars($event['slack_channel_url']) ?></td>
                <td>
                    <form method="post" style="display:inline-block">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <button type="button" class="btn btn-sm btn-warning" onclick="toggleEventEdit(<?= $event['id'] ?>)">編集</button>
                        <button type="submit" name="event_delete_submit" class="btn btn-sm btn-danger" onclick="return confirm('本当に削除しますか？')">削除</button>
                    </form>
                </td>
            </tr>
            <tr id="event_edit_row_<?= $event['id'] ?>" style="display:none">
                <td colspan="4">
                    <form method="post" class="row g-2 align-items-center">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <div class="col">
                            <input type="text" name="event_name" value="<?= htmlspecialchars($event['name']) ?>" class="form-control" required>
                        </div>
                        <div class="col">
                            <input type="number" name="event_budget" value="<?= htmlspecialchars($event['budget']) ?>" class="form-control" required>
                        </div>
                        <div class="col">
                            <input type="text" name="slack_channel_url" value="<?= htmlspecialchars($event['slack_channel_url']) ?>" class="form-control" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="event_edit_submit" class="btn btn-warning btn-sm">更新</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEventEdit(<?= $event['id'] ?>)">キャンセル</button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>チーム登録</h2>
    <form method="post" class="mb-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-2">
            <label>イベント選択</label>
            <select name="event_id" class="form-select" required>
                <option value="">選択してください</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= htmlspecialchars($event['id']) ?>">
                        <?= htmlspecialchars($event['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label>チーム名</label>
            <input type="text" name="team_name" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>チーム予算</label>
            <input type="number" name="team_budget" class="form-control" required>
        </div>
        <button type="submit" name="team_submit" class="btn btn-success">チーム登録</button>
    </form>

    <h3>チーム一覧</h3>
    <form method="post" class="mb-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <label>イベント選択</label>
        <select name="selected_event_id" class="form-select" onchange="this.form.submit()">
            <option value="">選択してください</option>
            <?php foreach ($events as $event): ?>
                <option value="<?= htmlspecialchars($event['id']) ?>" <?= ($selected_event_id == $event['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($event['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if ($selected_event_id): ?>
    <table class="table table-bordered mb-4">
        <thead><tr><th>名前</th><th>予算</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($teams as $team): ?>
            <tr>
                <td><?= htmlspecialchars($team['name']) ?></td>
                <td><?= htmlspecialchars($team['budget']) ?></td>
                <td>
                    <form method="post" style="display:inline-block">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                        <button type="button" class="btn btn-sm btn-warning" onclick="toggleTeamEdit(<?= $team['id'] ?>)">編集</button>
                        <button type="submit" name="team_delete_submit" class="btn btn-sm btn-danger" onclick="return confirm('本当に削除しますか？')">削除</button>
                    </form>
                </td>
            </tr>
            <tr id="team_edit_row_<?= $team['id'] ?>" style="display:none">
                <td colspan="3">
                    <form method="post" class="row g-2 align-items-center">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                        <div class="col">
                            <input type="text" name="team_name" value="<?= htmlspecialchars($team['name']) ?>" class="form-control" required>
                        </div>
                        <div class="col">
                            <input type="number" name="team_budget" value="<?= htmlspecialchars($team['budget']) ?>" class="form-control" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="team_edit_submit" class="btn btn-warning btn-sm">更新</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleTeamEdit(<?= $team['id'] ?>)">キャンセル</button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <script>
    function toggleEventEdit(id) {
        var row = document.getElementById('event_edit_row_' + id);
        if (row.style.display === '' || row.style.display === 'table-row') {
            row.style.display = 'none';
        } else {
            row.style.display = 'table-row';
        }
    }
    function toggleTeamEdit(id) {
        var row = document.getElementById('team_edit_row_' + id);
        if (row.style.display === '' || row.style.display === 'table-row') {
            row.style.display = 'none';
        } else {
            row.style.display = 'table-row';
        }
    }
    </script>
</body>
</html>
