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

// 使用予定用途追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_planned_expense'])) {
    $purpose = $_POST['purpose'] ?? '';
    $estimated_amount = $_POST['estimated_amount'] ?? 0;
    $person_name = $_POST['planned_person_name'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $purpose_category_id = $_POST['purpose_category_id'] ?? null;
    if ($purpose_category_id == '') $purpose_category_id = null;
    
    if ($purpose && $estimated_amount && $person_name) {
        $stmt = $pdo->prepare('INSERT INTO planned_expenses (team_id, purpose, purpose_category_id, estimated_amount, person_name, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$team_id, $purpose, $purpose_category_id, $estimated_amount, $person_name, $notes]);
        
        // 成功時はチームページにリダイレクト（ポップアップパラメータ付き）
        header('Location: team.php?team_id=' . $team_id . '&event_id=' . $event_id . '&success=planned_expense_added&popup=1');
        exit;
    } else {
        $error_message = '用途詳細、予定金額、担当者名は必須です。';
    }
}

// アクティブな用途カテゴリ一覧取得
$stmt = $pdo->prepare('SELECT * FROM purpose_categories WHERE is_active = 1 AND team_id = ? ORDER BY sort_order, name');
$stmt->execute([$team_id]);
$purpose_categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>使用予定用途追加 - <?= htmlspecialchars($team['name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/KFロゴ.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/KFロゴ.svg">
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#4A90E2">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <h1 class="title">📋 使用予定用途追加</h1>
            
            <div class="breadcrumb">
                <a href="team.php?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>">← <?= htmlspecialchars($team['name']) ?>に戻る</a>
                <span style="margin: 0 15px;">|</span>
                <span><?= htmlspecialchars($event['name']) ?> - <?= htmlspecialchars($team['name']) ?></span>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="post" class="form" id="plannedExpenseForm">
                    <div class="form-group">
                        <label>カテゴリ</label>
                        <select name="purpose_category_id" class="input">
                            <option value="">カテゴリを選択（任意）</option>
                            <?php foreach ($purpose_categories as $category): ?>
                                <option value="<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>用途詳細 <span class="required">*</span></label>
                        <input type="text" name="purpose" required class="input" placeholder="例：会場装飾用の花">
                    </div>
                    
                    <div class="form-group">
                        <label>担当者名 <span class="required">*</span></label>
                        <input type="text" name="planned_person_name" required class="input" placeholder="例：田中太郎">
                    </div>
                    
                    <div class="form-group">
                        <label>予定金額（円） <span class="required">*</span></label>
                        <input type="number" name="estimated_amount" required class="input" placeholder="5000" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>備考</label>
                        <textarea name="notes" class="input" rows="4" placeholder="詳細な内容や注意事項など（任意）"></textarea>
                    </div>
                    
                    <div class="notice">
                        <strong>📝 使用予定用途について</strong><br>
                        これから購入予定の物品や支払い予定の費用を登録します。実際に購入・支払いが完了したら「使用済み」として削除し、領収書を追加してください。
                    </div>
                    
                    <div class="form-actions">
                        <a href="team.php?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>" class="btn btn-outline">キャンセル</a>
                        <button type="submit" name="add_planned_expense" class="btn btn-success" id="submitPlannedExpenseBtn">追加</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .required {
            color: #e53e3e;
            font-weight: bold;
        }
        
        .notice {
            background-color: #e6f3ff;
            border: 1px solid #4a90e2;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
            color: #2c5aa0;
            font-weight: bold;
            text-align: center;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin: 16px 0;
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
</body>
</html>