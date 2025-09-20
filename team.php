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

// 領収書追加処理は専用ページ（add_receipt.php）に移動

// 領収書編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_receipt'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $person_name = $_POST['person_name'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $purpose_category_id = $_POST['purpose_category_id'] ?? null;
    if ($purpose_category_id == '') $purpose_category_id = null;
    
    if ($edit_id && $amount && $person_name) {
        $stmt = $pdo->prepare('UPDATE receipts SET person_name=?, purpose=?, purpose_category_id=?, amount=? WHERE id=?');
        $stmt->execute([$person_name, $purpose, $purpose_category_id, $amount, $edit_id]);
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

// 使用予定用途追加処理は専用ページ（add_planned_expense.php）に移動

// 使用予定用途編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_planned_expense'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $estimated_amount = $_POST['estimated_amount'] ?? 0;
    $person_name = $_POST['planned_person_name'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $purpose_category_id = $_POST['purpose_category_id'] ?? null;
    if ($purpose_category_id == '') $purpose_category_id = null;
    
    if ($edit_id && $purpose && $estimated_amount && $person_name) {
        $stmt = $pdo->prepare('UPDATE planned_expenses SET purpose=?, purpose_category_id=?, estimated_amount=?, person_name=?, notes=? WHERE id=?');
        $stmt->execute([$purpose, $purpose_category_id, $estimated_amount, $person_name, $notes, $edit_id]);
        header('Location: team.php?team_id=' . $team_id . '&event_id=' . $event_id);
        exit;
    }
}

// 使用予定用途削除処理（使用済みマーク）
if (isset($_GET['delete_planned_expense'])) {
    $delete_id = $_GET['delete_planned_expense'];
    $stmt = $pdo->prepare('DELETE FROM planned_expenses WHERE id=?');
    $stmt->execute([$delete_id]);
    header('Location: team.php?team_id=' . $team_id . '&event_id=' . $event_id);
    exit;
}

// 領収書受け取り状況切り替え処理（AJAX対応）
if (isset($_GET['toggle_receipt_status']) && isset($_GET['ajax'])) {
    $receipt_id = $_GET['toggle_receipt_status'];
    $stmt = $pdo->prepare('UPDATE receipts SET is_received = NOT is_received WHERE id = ? AND team_id = ?');
    $stmt->execute([$receipt_id, $team_id]);
    
    // 更新後の状態を取得
    $stmt = $pdo->prepare('SELECT is_received FROM receipts WHERE id = ? AND team_id = ?');
    $stmt->execute([$receipt_id, $team_id]);
    $receipt = $stmt->fetch();
    
    if ($receipt) {
        echo json_encode([
            'success' => true,
            'is_received' => (bool)$receipt['is_received']
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// 領収書受け取り状況切り替え処理（通常のページリロード、フォールバック用）
if (isset($_GET['toggle_receipt_status'])) {
    $receipt_id = $_GET['toggle_receipt_status'];
    $stmt = $pdo->prepare('UPDATE receipts SET is_received = NOT is_received WHERE id = ? AND team_id = ?');
    $stmt->execute([$receipt_id, $team_id]);
    header('Location: team.php?team_id=' . $team_id . '&event_id=' . $event_id);
    exit;
}

// 領収書一覧取得（用途カテゴリと受け取り状況も含む）
$stmt = $pdo->prepare('
    SELECT r.*, pc.name as category_name, pc.color as category_color 
    FROM receipts r 
    LEFT JOIN purpose_categories pc ON r.purpose_category_id = pc.id 
    WHERE r.team_id = ? 
    ORDER BY r.is_received ASC, r.id DESC
');
$stmt->execute([$team_id]);
$receipts = $stmt->fetchAll();

// 使用予定用途一覧取得（用途カテゴリも含む）
$stmt = $pdo->prepare('
    SELECT pe.*, pc.name as category_name, pc.color as category_color 
    FROM planned_expenses pe 
    LEFT JOIN purpose_categories pc ON pe.purpose_category_id = pc.id 
    WHERE pe.team_id = ? 
    ORDER BY pe.id DESC
');
$stmt->execute([$team_id]);
$planned_expenses = $stmt->fetchAll();

// アクティブな用途カテゴリ一覧取得
$stmt = $pdo->prepare('SELECT * FROM purpose_categories WHERE is_active = 1 AND team_id = ? ORDER BY sort_order, name');
$stmt->execute([$team_id]);
$purpose_categories = $stmt->fetchAll();

// カテゴリごとの集計データを取得（予算情報含む）
$stmt = $pdo->prepare('
    SELECT 
        pc.id, pc.name, pc.color, pc.budget_allocation,
        COALESCE(SUM(r.amount), 0) as total_receipts,
        COALESCE(SUM(pe.estimated_amount), 0) as total_planned,
        COUNT(DISTINCT r.id) as receipt_count,
        COUNT(DISTINCT pe.id) as planned_count
    FROM purpose_categories pc
    LEFT JOIN receipts r ON pc.id = r.purpose_category_id AND r.team_id = ?
    LEFT JOIN planned_expenses pe ON pc.id = pe.purpose_category_id AND pe.team_id = ?
    WHERE pc.is_active = 1 AND pc.team_id = ?
    GROUP BY pc.id, pc.name, pc.color, pc.budget_allocation
    HAVING (total_receipts > 0 OR total_planned > 0 OR receipt_count > 0 OR planned_count > 0 OR pc.budget_allocation > 0)
    ORDER BY pc.sort_order, pc.name
');
$stmt->execute([$team_id, $team_id, $team_id]);
$category_stats = $stmt->fetchAll();

// 未分類の集計
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as total_receipts, COUNT(*) as receipt_count FROM receipts WHERE team_id = ? AND purpose_category_id IS NULL');
$stmt->execute([$team_id]);
$uncategorized_receipts = $stmt->fetch();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(estimated_amount), 0) as total_planned, COUNT(*) as planned_count FROM planned_expenses WHERE team_id = ? AND purpose_category_id IS NULL');
$stmt->execute([$team_id]);
$uncategorized_planned = $stmt->fetch();

$uncategorized_stats = [
    'total_receipts' => $uncategorized_receipts['total_receipts'],
    'total_planned' => $uncategorized_planned['total_planned'],
    'receipt_count' => $uncategorized_receipts['receipt_count'],
    'planned_count' => $uncategorized_planned['planned_count']
];

// チームの領収書総額
$stmt = $pdo->prepare('SELECT SUM(amount) as total FROM receipts WHERE team_id = ?');
$stmt->execute([$team_id]);
$used_total = $stmt->fetchColumn() ?: 0;

// チームの使用予定用途総額
$stmt = $pdo->prepare('SELECT SUM(estimated_amount) as total FROM planned_expenses WHERE team_id = ?');
$stmt->execute([$team_id]);
$planned_total = $stmt->fetchColumn() ?: 0;

$budget_left = $team['budget'] - $used_total;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チーム詳細 - <?= htmlspecialchars($team['name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/KFロゴ.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/KFロゴ.svg">
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#4A90E2">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <h1 class="title">👥 <?= htmlspecialchars($team['name']) ?></h1>
            
            <div class="breadcrumb">
                <a href="event.php?event_id=<?= $event_id ?>">← <?= htmlspecialchars($event['name']) ?>に戻る</a>
                <span style="margin: 0 15px;">|</span>
                <a href="budget_overview.php?event_id=<?= $event_id ?>">📊 予算管理概要</a>
                <span style="margin: 0 15px;">|</span>
                <a href="purpose_categories.php?team_id=<?= $team_id ?>">🏷️ カテゴリ管理</a>
            </div>

            <?php
            // 成功メッセージの表示
            if (isset($_GET['success'])) {
                $success_messages = [
                    'receipt_added' => '領収書が正常に追加されました。',
                    'planned_expense_added' => '使用予定用途が正常に追加されました。'
                ];
                $message = $success_messages[$_GET['success']] ?? '処理が正常に完了しました。';
                echo "<div class='alert alert-success'>" . htmlspecialchars($message) . "</div>";
                
                // ポップアップ表示フラグ
                if (isset($_GET['popup']) && $_GET['popup'] == '1') {
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            alert('" . addslashes($message) . "');
                        });
                    </script>";
                }
            }
            ?>

            <div class="info-cards">
                <div class="info-card budget">
                    <h3>チーム予算</h3>
                    <div class="amount">¥<?= number_format($team['budget']) ?></div>
                </div>
                <div class="info-card used">
                    <h3>使用総額</h3>
                    <div class="amount">¥<?= number_format($used_total) ?></div>
                </div>
                <div class="info-card planned">
                    <h3>使用予定額</h3>
                    <div class="amount">¥<?= number_format($planned_total) ?></div>
                </div>
                <div class="info-card remaining">
                    <h3>予算残り</h3>
                    <div class="amount">¥<?= number_format($budget_left) ?></div>
                </div>
            </div>

            <h2 class="subtitle">📊 カテゴリ別集計</h2>
            <div class="category-stats">
                <?php foreach ($category_stats as $stat): 
                    $budget_remaining = $stat['budget_allocation'] - ($stat['total_receipts'] + $stat['total_planned']);
                    $is_over_budget = $stat['budget_allocation'] > 0 && $budget_remaining < 0;
                    $budget_usage_percent = $stat['budget_allocation'] > 0 ? (($stat['total_receipts'] + $stat['total_planned']) / $stat['budget_allocation']) * 100 : 0;
                ?>
                <div class="category-stat-card <?= $is_over_budget ? 'over-budget' : '' ?>" style="border-left: 4px solid <?= htmlspecialchars($stat['color']) ?>;">
                    <div class="category-header">
                        <span class="category-name" style="color: <?= htmlspecialchars($stat['color']) ?>;">
                            <?= htmlspecialchars($stat['name']) ?>
                        </span>
                        <span class="category-counts">
                            領収書: <?= $stat['receipt_count'] ?>件 | 予定: <?= $stat['planned_count'] ?>件
                        </span>
                    </div>
                    
                    <?php if ($stat['budget_allocation'] > 0): ?>
                        <div class="budget-info">
                            <div class="budget-bar">
                                <div class="budget-used" style="width: <?= min(100, $budget_usage_percent) ?>%; background-color: <?= $is_over_budget ? '#e53e3e' : $stat['color'] ?>;"></div>
                            </div>
                            <div class="budget-details">
                                <span class="budget-allocated">予算: ¥<?= number_format($stat['budget_allocation']) ?></span>
                                <span class="budget-remaining <?= $is_over_budget ? 'over-budget' : '' ?>">
                                    残り: ¥<?= number_format($budget_remaining) ?>
                                </span>
                                <span class="budget-percent"><?= number_format($budget_usage_percent, 1) ?>%</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="category-amounts">
                        <span class="used-amount">使用済み: ¥<?= number_format($stat['total_receipts']) ?></span>
                        <span class="planned-amount">予定額: ¥<?= number_format($stat['total_planned']) ?></span>
                        <span class="total-amount">合計: ¥<?= number_format($stat['total_receipts'] + $stat['total_planned']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if ($uncategorized_stats['receipt_count'] > 0 || $uncategorized_stats['planned_count'] > 0): ?>
                <div class="category-stat-card uncategorized" style="border-left: 4px solid #999;">
                    <div class="category-header">
                        <span class="category-name" style="color: #999;">未分類</span>
                        <span class="category-counts">
                            領収書: <?= $uncategorized_stats['receipt_count'] ?>件 | 予定: <?= $uncategorized_stats['planned_count'] ?>件
                        </span>
                    </div>
                    <div class="category-amounts">
                        <span class="used-amount">使用済み: ¥<?= number_format($uncategorized_stats['total_receipts']) ?></span>
                        <span class="planned-amount">予定額: ¥<?= number_format($uncategorized_stats['total_planned']) ?></span>
                        <span class="total-amount">合計: ¥<?= number_format($uncategorized_stats['total_receipts'] + $uncategorized_stats['total_planned']) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <h2 class="subtitle">🧾 領収書一覧</h2>
            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>受け取り</th>
                            <th>個人名</th>
                            <th>カテゴリ</th>
                            <th>用途詳細</th>
                            <th>金額</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td>
                                <button onclick="toggleReceiptStatus(<?= $receipt['id'] ?>, this)" 
                                        class="btn <?= $receipt['is_received'] ? 'btn-success' : 'btn-outline' ?> btn-sm receipt-toggle"
                                        data-receipt-id="<?= $receipt['id'] ?>"
                                        data-received="<?= $receipt['is_received'] ? '1' : '0' ?>">
                                    <?= $receipt['is_received'] ? '✓ 済' : '○ 未' ?>
                                </button>
                            </td>
                            <td><strong><?= htmlspecialchars($receipt['person_name']) ?></strong></td>
                            <td>
                                <?php if ($receipt['category_name']): ?>
                                    <span class="category-tag" style="background-color: <?= htmlspecialchars($receipt['category_color']) ?>20; color: <?= htmlspecialchars($receipt['category_color']) ?>; border-left: 3px solid <?= htmlspecialchars($receipt['category_color']) ?>;">
                                        <?= htmlspecialchars($receipt['category_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="no-category">未分類</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($receipt['purpose']) ?></td>
                            <td><strong>¥<?= number_format($receipt['amount']) ?></strong></td>
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

            <h2 class="subtitle">📋 使用予定用途一覧</h2>
            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>カテゴリ</th>
                            <th>用途詳細</th>
                            <th>担当者</th>
                            <th>予定金額</th>
                            <th>備考</th>
                            <th>登録日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($planned_expenses)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #718096;">使用予定用途が登録されていません</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($planned_expenses as $expense): ?>
                        <tr>
                            <td>
                                <?php if ($expense['category_name']): ?>
                                    <span class="category-tag" style="background-color: <?= htmlspecialchars($expense['category_color']) ?>20; color: <?= htmlspecialchars($expense['category_color']) ?>; border-left: 3px solid <?= htmlspecialchars($expense['category_color']) ?>;">
                                        <?= htmlspecialchars($expense['category_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="no-category">未分類</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($expense['purpose']) ?></strong></td>
                            <td><?= htmlspecialchars($expense['person_name']) ?></td>
                            <td><strong>¥<?= number_format($expense['estimated_amount']) ?></strong></td>
                            <td><?= htmlspecialchars($expense['notes'] ?? '') ?></td>
                            <td><?= htmlspecialchars(date('Y/m/d H:i', strtotime($expense['created_at']))) ?></td>
                            <td>
                                <a href="?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>&edit_planned_expense=<?= $expense['id'] ?>" class="btn btn-edit btn-sm">編集</a>
                                <a href="?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>&delete_planned_expense=<?= $expense['id'] ?>" 
                                   onclick="return confirm('使用済みにして削除しますか？');" 
                                   class="btn btn-success btn-sm">使用済み</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
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
                <form method="post" class="form">
                    <input type="hidden" name="edit_id" value="<?= $edit_receipt['id'] ?>">
                    <div class="form-group">
                        <label>個人名</label>
                        <input type="text" name="person_name" value="<?= htmlspecialchars($edit_receipt['person_name']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>カテゴリ</label>
                        <select name="purpose_category_id" class="input">
                            <option value="">カテゴリを選択（任意）</option>
                            <?php foreach ($purpose_categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= ($edit_receipt['purpose_category_id'] == $category['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>用途詳細</label>
                        <input type="text" name="purpose" value="<?= htmlspecialchars($edit_receipt['purpose']) ?>" class="input" placeholder="例：会場装飾用品">
                    </div>
                    <div class="form-group">
                        <label>金額（円）</label>
                        <input type="number" name="amount" value="<?= htmlspecialchars($edit_receipt['amount']) ?>" required class="input">
                    </div>
                    <div class="text-right">
                        <a href="team.php?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>" class="btn btn-outline">キャンセル</a>
                        <button type="submit" name="update_receipt" class="btn btn-primary">更新</button>
                    </div>
                </form>
                <?php endif;
            } elseif (isset($_GET['edit_planned_expense'])) {
                $edit_id = $_GET['edit_planned_expense'];
                $stmt = $pdo->prepare('SELECT * FROM planned_expenses WHERE id = ?');
                $stmt->execute([$edit_id]);
                $edit_expense = $stmt->fetch();
                if ($edit_expense): ?>
                <h2 class="subtitle">✏️ 使用予定用途編集</h2>
                <form method="post" class="form">
                    <input type="hidden" name="edit_id" value="<?= $edit_expense['id'] ?>">
                    <div class="form-group">
                        <label>カテゴリ</label>
                        <select name="purpose_category_id" class="input">
                            <option value="">カテゴリを選択（任意）</option>
                            <?php foreach ($purpose_categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= ($edit_expense['purpose_category_id'] == $category['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>用途詳細</label>
                        <input type="text" name="purpose" value="<?= htmlspecialchars($edit_expense['purpose']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>担当者名</label>
                        <input type="text" name="planned_person_name" value="<?= htmlspecialchars($edit_expense['person_name']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>予定金額（円）</label>
                        <input type="number" name="estimated_amount" value="<?= htmlspecialchars($edit_expense['estimated_amount']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>備考</label>
                        <textarea name="notes" class="input" rows="3" placeholder="詳細な内容や注意事項など"><?= htmlspecialchars($edit_expense['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="text-right">
                        <a href="team.php?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>" class="btn btn-outline">キャンセル</a>
                        <button type="submit" name="update_planned_expense" class="btn btn-primary">更新</button>
                    </div>
                </form>
                <?php endif;
            } else { ?>
                <!-- 追加アクション用のナビゲーション -->
                <div class="add-actions">
                    <h2 class="subtitle">追加・管理</h2>
                    <div class="action-links">
                        <a href="add_receipt.php?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>" class="btn btn-success">
                            ➕ 新しい領収書を追加
                        </a>
                        <a href="add_planned_expense.php?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>" class="btn btn-info">
                            📝 新しい使用予定用途を追加
                        </a>
                        <a href="purpose_categories.php?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>" class="btn btn-outline">
                            🏷️ カテゴリ管理
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <style>
        .category-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .no-category {
            color: #888;
            font-style: italic;
            font-size: 0.85em;
        }
        
        .receipt-toggle {
            min-width: 60px;
            text-align: center;
        }
        
        .receipt-toggle:hover {
            opacity: 0.8;
        }
        
        .receipt-toggle:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .category-stats {
            display: grid;
            gap: 12px;
            margin: 20px 0;
        }
        
        .category-stat-card {
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .category-name {
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .category-counts {
            font-size: 0.9em;
            color: #666;
        }
        
        .category-amounts {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 0.95em;
        }
        
        .used-amount {
            color: #e53e3e;
        }
        
        .planned-amount {
            color: #3182ce;
        }
        
        .total-amount {
            font-weight: bold;
            color: #1a202c;
        }
        
        .uncategorized {
            background-color: #f9f9f9;
        }
        
        .budget-info {
            margin: 12px 0;
        }
        
        .budget-bar {
            width: 100%;
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .budget-used {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .budget-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
        }
        
        .budget-allocated {
            color: #4a5568;
            font-weight: bold;
        }
        
        .budget-remaining {
            color: #38a169;
            font-weight: bold;
        }
        
        .budget-remaining.over-budget {
            color: #e53e3e;
        }
        
        .budget-percent {
            color: #718096;
        }
        
        .category-stat-card.over-budget {
            background-color: #fed7d7;
            border-color: #e53e3e !important;
        }
        
        .add-actions {
            margin: 24px 0;
        }
        
        .action-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        
        .action-links .btn {
            flex: 1;
            min-width: 200px;
            text-align: center;
            padding: 12px 16px;
            font-weight: bold;
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
        
        @media (max-width: 768px) {
            .action-links {
                flex-direction: column;
            }
            
            .action-links .btn {
                flex: none;
                min-width: none;
            }
        }
        
        @media (max-width: 768px) {
            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            
            .category-amounts {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>

    <script>
        function toggleReceiptStatus(receiptId, button) {
            // ボタンを無効化
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = '処理中...';
            
            // AJAX リクエスト
            fetch(`?team_id=<?= $team_id ?>&event_id=<?= $event_id ?>&toggle_receipt_status=${receiptId}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // ボタンの表示を更新
                        if (data.is_received) {
                            button.className = 'btn btn-success btn-sm receipt-toggle';
                            button.textContent = '✓ 済';
                            button.setAttribute('data-received', '1');
                        } else {
                            button.className = 'btn btn-outline btn-sm receipt-toggle';
                            button.textContent = '○ 未';
                            button.setAttribute('data-received', '0');
                        }
                    } else {
                        // エラーの場合は元に戻す
                        button.textContent = originalText;
                        alert('エラーが発生しました。ページを更新してください。');
                    }
                })
                .catch(error => {
                    // ネットワークエラー等の場合
                    button.textContent = originalText;
                    alert('通信エラーが発生しました。ページを更新してください。');
                })
                .finally(() => {
                    // ボタンを再有効化
                    button.disabled = false;
                });
        }

        /* 追加フォーム関連のJavaScript関数は専用ページに移動 */
    </script>
</body>
</html>
