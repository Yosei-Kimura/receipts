<?php
require_once __DIR__ . '/config/db_connect.php';

// イベント追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $name = $_POST['name'] ?? '';
    $budget = $_POST['budget'] ?? 0;
    $password = $_POST['password'] ?? '';
    
    if ($name && $budget && $password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO events (name, budget, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $budget, $password_hash]);
        $success_message = 'イベントを作成しました。';
    } else {
        $error_message = 'イベント名、予算、パスワードは必須です。';
    }
}

// イベント編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $budget = $_POST['budget'] ?? 0;
    $new_password = $_POST['new_password'] ?? '';
    
    if ($edit_id && $name && $budget) {
        if (!empty($new_password)) {
            // パスワードも変更する場合
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE events SET name=?, budget=?, password_hash=? WHERE id=?');
            $stmt->execute([$name, $budget, $password_hash, $edit_id]);
        } else {
            // パスワードは変更しない場合
            $stmt = $pdo->prepare('UPDATE events SET name=?, budget=? WHERE id=?');
            $stmt->execute([$name, $budget, $edit_id]);
        }
        $success_message = 'イベントを更新しました。';
    } else {
        $error_message = 'イベント名と予算は必須です。';
    }
}

// イベント削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $delete_id = $_POST['delete_id'] ?? '';
    $password = $_POST['delete_password'] ?? '';
    
    if ($delete_id && $password) {
        // イベントのパスワードハッシュを取得
        $stmt = $pdo->prepare('SELECT password_hash FROM events WHERE id = ?');
        $stmt->execute([$delete_id]);
        $event = $stmt->fetch();
        
        if ($event && password_verify($password, $event['password_hash'])) {
            // パスワードが正しい場合、削除実行
            $stmt = $pdo->prepare('DELETE FROM events WHERE id=?');
            $stmt->execute([$delete_id]);
            $success_message = 'イベントを削除しました。';
        } else {
            $error_message = 'パスワードが正しくありません。';
        }
    } else {
        $error_message = 'パスワードの入力が必要です。';
    }
}

// イベント一覧取得
$events = $pdo->query('SELECT * FROM events ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KFイベント予算管理</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/KFロゴ.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/KFロゴ.svg">
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#FF8C00">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <h1 class="title">KFイベント予算管理</h1>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>イベント名</th>
                            <th>予算</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($event['name']) ?></strong></td>
                            <td>¥<?= number_format($event['budget']) ?></td>
                            <td>
                                <a href="event.php?event_id=<?= $event['id'] ?>" class="btn btn-primary btn-sm">選択</a>
                                <a href="budget_overview.php?event_id=<?= $event['id'] ?>" class="btn btn-info btn-sm">予算概要</a>
                                <a href="?edit_event=<?= $event['id'] ?>" class="btn btn-edit btn-sm">編集</a>
                                <button type="button" onclick="showDeleteForm(<?= $event['id'] ?>, '<?= htmlspecialchars($event['name'], ENT_QUOTES) ?>')" class="btn btn-delete btn-sm">削除</button>
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
                        <label>新しいパスワード（変更しない場合は空白）</label>
                        <input type="password" name="new_password" class="input" placeholder="新しいパスワードを入力（任意）">
                        <small class="form-help">パスワードはイベント削除時に必要になります</small>
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
                        <label>パスワード</label>
                        <input type="password" name="password" required class="input" placeholder="削除時に必要なパスワード">
                        <small class="form-help">このパスワードはイベント削除時に必要になります。忘れないように記録してください。</small>
                    </div>
                    <div class="text-right">
                        <button type="submit" name="add_event" class="btn btn-success">追加</button>
                    </div>
                </form>
            <?php } ?>
            
            <!-- 削除確認フォーム（モーダル） -->
            <div id="deleteModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <h3>🚨 イベント削除確認</h3>
                    <p>イベント「<span id="deleteEventName"></span>」を削除します。</p>
                    <p><strong>この操作は取り消せません。関連するすべてのチーム、領収書、予定も削除されます。</strong></p>
                    
                    <form method="post">
                        <input type="hidden" name="delete_id" id="deleteEventId">
                        <div class="form-group">
                            <label>削除パスワード</label>
                            <input type="password" name="delete_password" required class="input" placeholder="イベント作成時に設定したパスワード">
                        </div>
                        <div class="text-right">
                            <button type="button" onclick="hideDeleteForm()" class="btn btn-outline">キャンセル</button>
                            <button type="submit" name="delete_event" class="btn btn-delete">削除実行</button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>

    <style>
        .form-help {
            display: block;
            color: #666;
            font-size: 0.85em;
            margin-top: 4px;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .modal-content h3 {
            margin-top: 0;
            color: #e53e3e;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin: 16px 0;
            font-weight: bold;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>

    <?php if (isset($success_message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            alert('<?= addslashes($success_message) ?>');
        });
    </script>
    <?php endif; ?>

    <script>
        function showDeleteForm(eventId, eventName) {
            document.getElementById('deleteEventId').value = eventId;
            document.getElementById('deleteEventName').textContent = eventName;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function hideDeleteForm() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // モーダルの背景クリックで閉じる
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteForm();
            }
        });
    </script>
</body>
</html>
