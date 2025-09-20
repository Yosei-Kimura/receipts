<?php
require_once __DIR__ . '/config/db_connect.php';

$team_id = $_GET['team_id'] ?? null;
if (!$team_id) {
    header('Location: index.php');
    exit;
}

// チーム情報取得
$stmt = $pdo->prepare('SELECT t.*, e.name as event_name FROM teams t JOIN events e ON t.event_id = e.id WHERE t.id = ?');
$stmt->execute([$team_id]);
$team = $stmt->fetch();
if (!$team) {
    echo 'チームが見つかりません';
    exit;
}

// 用途カテゴリ追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $color = '#4A90E2'; // 固定値
    $sort_order = 0; // 固定値
    $budget_allocation = $_POST['budget_allocation'] ?? 0;
    
    if ($name) {
        // 予算配分の合計チェック
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(budget_allocation), 0) as total_allocated FROM purpose_categories WHERE team_id = ? AND is_active = 1');
        $stmt->execute([$team_id]);
        $current_allocated = $stmt->fetchColumn();
        
        if (($current_allocated + $budget_allocation) > $team['budget']) {
            $error_message = 'チーム予算を超える配分はできません。現在の配分済み額: ¥' . number_format($current_allocated) . '、チーム予算: ¥' . number_format($team['budget']);
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO purpose_categories (team_id, name, description, color, sort_order, budget_allocation, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
                $stmt->execute([$team_id, $name, $description, $color, $sort_order, $budget_allocation]);
                $success_message = 'カテゴリを追加しました。';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $error_message = '同じ名前のカテゴリがすでに存在します。';
                } else {
                    $error_message = 'エラーが発生しました: ' . $e->getMessage();
                }
            }
        }
    } else {
        $error_message = 'カテゴリ名は必須です。';
    }
}

// 用途カテゴリ更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $color = '#4A90E2'; // 固定値
    $sort_order = 0; // 固定値
    $budget_allocation = $_POST['budget_allocation'] ?? 0;
    $is_active = 1; // 常にアクティブ
    
    if ($id && $name) {
        // 現在の配分額を除いた合計をチェック
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(budget_allocation), 0) as total_allocated FROM purpose_categories WHERE team_id = ? AND is_active = 1 AND id != ?');
        $stmt->execute([$team_id, $id]);
        $current_allocated = $stmt->fetchColumn();
        
        if (($current_allocated + $budget_allocation) > $team['budget']) {
            $error_message = 'チーム予算を超える配分はできません。他のカテゴリの配分済み額: ¥' . number_format($current_allocated) . '、チーム予算: ¥' . number_format($team['budget']);
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE purpose_categories SET name=?, description=?, color=?, sort_order=?, budget_allocation=?, is_active=? WHERE id=? AND team_id=?');
                $stmt->execute([$name, $description, $color, $sort_order, $budget_allocation, $is_active, $id, $team_id]);
                $success_message = 'カテゴリを更新しました。';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $error_message = '同じ名前のカテゴリがすでに存在します。';
                } else {
                    $error_message = 'エラーが発生しました: ' . $e->getMessage();
                }
            }
        }
    }
}

// 用途カテゴリ削除処理
if (isset($_GET['delete_category'])) {
    $delete_id = $_GET['delete_category'];
    
    // 使用中のカテゴリかチェック
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM receipts WHERE purpose_category_id = ?');
    $stmt->execute([$delete_id]);
    $receipt_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM planned_expenses WHERE purpose_category_id = ?');
    $stmt->execute([$delete_id]);
    $expense_count = $stmt->fetchColumn();
    
    if ($receipt_count > 0 || $expense_count > 0) {
        $error_message = 'このカテゴリは領収書や予定用途で使用中のため削除できません。非アクティブにしてください。';
    } else {
        $stmt = $pdo->prepare('DELETE FROM purpose_categories WHERE id=? AND team_id=?');
        $stmt->execute([$delete_id, $team_id]);
        $success_message = 'カテゴリを削除しました。';
    }
}

// 用途カテゴリ一覧取得
// categories に対して各カテゴリの使用済み金額（receipts.amount + planned_expenses.estimated_amount）を結合して取得
$stmt = $pdo->prepare(
    'SELECT pc.*, 
            COALESCE(r.total_receipts, 0) AS total_receipts, 
            COALESCE(pe.total_planned, 0) AS total_planned, 
            (COALESCE(r.total_receipts, 0) + COALESCE(pe.total_planned, 0)) AS used_budget
     FROM purpose_categories pc
     LEFT JOIN (
         SELECT purpose_category_id, SUM(amount) AS total_receipts
         FROM receipts
         WHERE team_id = ?
         GROUP BY purpose_category_id
     ) r ON pc.id = r.purpose_category_id
     LEFT JOIN (
         SELECT purpose_category_id, SUM(estimated_amount) AS total_planned
         FROM planned_expenses
         WHERE team_id = ?
         GROUP BY purpose_category_id
     ) pe ON pc.id = pe.purpose_category_id
     WHERE pc.team_id = ?
     ORDER BY pc.name'
);
$stmt->execute([$team_id, $team_id, $team_id]);
$categories = $stmt->fetchAll();

// 予算配分の合計を取得
$stmt = $pdo->prepare('SELECT COALESCE(SUM(budget_allocation), 0) as total_allocated FROM purpose_categories WHERE team_id = ? AND is_active = 1');
$stmt->execute([$team_id]);
$total_allocated = $stmt->fetchColumn();
$unallocated_budget = $team['budget'] - $total_allocated;

// 編集対象のカテゴリ取得
$edit_category = null;
if (isset($_GET['edit_category'])) {
    $edit_id = $_GET['edit_category'];
    $stmt = $pdo->prepare('SELECT * FROM purpose_categories WHERE id = ? AND team_id = ?');
    $stmt->execute([$edit_id, $team_id]);
    $edit_category = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($team['name']) ?>の用途カテゴリ管理</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/KFロゴ.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/KFロゴ.svg">
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#4A90E2">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <h1 class="title">📝 <?= htmlspecialchars($team['name']) ?>のカテゴリ管理</h1>
            
            <div class="breadcrumb">
                <a href="team.php?team_id=<?= $team_id ?>&event_id=<?= $team['event_id'] ?>">← <?= htmlspecialchars($team['name']) ?>に戻る</a>
                <span style="margin: 0 15px;">|</span>
                <span><?= htmlspecialchars($team['event_name']) ?> - <?= htmlspecialchars($team['name']) ?></span>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <div class="budget-overview">
                <h2 class="subtitle">💰 予算配分概要</h2>
                <div class="budget-cards">
                    <div class="budget-card">
                        <h3>チーム総予算</h3>
                        <div class="budget-amount">¥<?= number_format($team['budget']) ?></div>
                    </div>
                    <div class="budget-card">
                        <h3>配分済み予算</h3>
                        <div class="budget-amount allocated">¥<?= number_format($total_allocated) ?></div>
                    </div>
                    <div class="budget-card">
                        <h3>未配分予算</h3>
                        <div class="budget-amount <?= $unallocated_budget < 0 ? 'over-budget' : 'available' ?>">
                            ¥<?= number_format($unallocated_budget) ?>
                        </div>
                    </div>
                </div>
                <?php if ($unallocated_budget < 0): ?>
                    <div class="alert alert-error">
                        ⚠️ 配分額がチーム予算を ¥<?= number_format(abs($unallocated_budget)) ?> 超過しています。
                    </div>
                <?php endif; ?>
            </div>

            <h2 class="subtitle">🏷️ カテゴリ一覧</h2>
            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>カテゴリ名</th>
                            <th>説明</th>
                            <th>予算配分</th>
                            <th>使用済み予算</th>
                            <th>使用数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): 
                            // 使用数カウント
                            $stmt = $pdo->prepare('SELECT COUNT(*) FROM receipts WHERE purpose_category_id = ?');
                            $stmt->execute([$category['id']]);
                            $receipt_count = $stmt->fetchColumn();
                            
                            $stmt = $pdo->prepare('SELECT COUNT(*) FROM planned_expenses WHERE purpose_category_id = ?');
                            $stmt->execute([$category['id']]);
                            $expense_count = $stmt->fetchColumn();
                            $total_usage = $receipt_count + $expense_count;
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($category['name']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($category['description'] ?? '') ?></td>
                            <td>
                                <strong class="budget-allocation">¥<?= number_format($category['budget_allocation']) ?></strong>
                            </td>
                            <td>
                                <strong class="used-budget">¥<?= number_format($category['used_budget']) ?></strong>
                            </td>
                            <td><?= $total_usage ?>件</td>
                            <td>
                                <a href="?team_id=<?= $team_id ?>&edit_category=<?= $category['id'] ?>" class="btn btn-edit btn-sm">編集</a>
                                <?php if ($total_usage == 0): ?>
                                    <a href="?team_id=<?= $team_id ?>&delete_category=<?= $category['id'] ?>" 
                                       onclick="return confirm('本当に削除しますか？');" 
                                       class="btn btn-delete btn-sm">削除</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($edit_category): ?>
                <h2 class="subtitle">✏️ カテゴリ編集</h2>
                <form method="post" class="form">
                    <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
                    <div class="form-group">
                        <label>カテゴリ名</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($edit_category['name']) ?>" required class="input">
                    </div>
                    <div class="form-group">
                        <label>説明</label>
                        <textarea name="description" class="input" rows="2"><?= htmlspecialchars($edit_category['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>予算配分（円）</label>
                        <input type="number" name="budget_allocation" value="<?= htmlspecialchars($edit_category['budget_allocation']) ?>" class="input" min="0">
                        <small class="form-help">0円の場合は予算制限なし</small>
                    </div>
                    <div class="text-right">
                        <a href="purpose_categories.php?team_id=<?= $team_id ?>" class="btn btn-outline">キャンセル</a>
                        <button type="submit" name="update_category" class="btn btn-primary">更新</button>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="subtitle">➕ 新しいカテゴリ追加</h2>
                <form method="post" class="form" id="categoryForm">
                    <div class="form-group">
                        <label>カテゴリ名</label>
                        <input type="text" name="name" required class="input" placeholder="例：食材・飲料">
                    </div>
                    <div class="form-group">
                        <label>説明</label>
                        <textarea name="description" class="input" rows="2" placeholder="このカテゴリの説明（任意）"></textarea>
                    </div>
                    <div class="form-group">
                        <label>予算配分（円）</label>
                        <input type="number" name="budget_allocation" value="0" class="input" min="0">
                        <small class="form-help">0円の場合は予算制限なし。残り予算: ¥<?= number_format(max(0, $unallocated_budget)) ?></small>
                    </div>
                    <div class="text-right">
                        <button type="submit" name="add_category" class="btn btn-success" id="submitCategoryBtn">追加</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <style>
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
        
        .budget-overview {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .budget-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        
        .budget-card {
            background: white;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .budget-card h3 {
            margin: 0 0 8px 0;
            font-size: 0.9em;
            color: #666;
        }
        
        .budget-amount {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }
        
        .budget-amount.allocated {
            color: #3182ce;
        }
        
        .budget-amount.available {
            color: #38a169;
        }
        
        .budget-amount.over-budget {
            color: #e53e3e;
        }
        
        .budget-allocation {
            color: #3182ce;
        }
        
        .form-help {
            display: block;
            margin-top: 4px;
            color: #666;
            font-size: 0.85em;
        }
    </style>

    <?php if (isset($success_message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            alert('<?= addslashes($success_message) ?>');
        });
    </script>
    <?php endif; ?>
</body>
</html>