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

// イベント全体の予算情報を取得
$stmt = $pdo->prepare('
    SELECT 
        SUM(t.budget) as total_team_budget,
        COALESCE(SUM(r.amount), 0) as total_used_amount,
        COALESCE(SUM(pe.estimated_amount), 0) as total_planned_amount
    FROM teams t
    LEFT JOIN receipts r ON t.id = r.team_id
    LEFT JOIN planned_expenses pe ON t.id = pe.team_id
    WHERE t.event_id = ?
');
$stmt->execute([$event_id]);
$event_totals = $stmt->fetch();

// チームごとの詳細情報を取得
$stmt = $pdo->prepare('
    SELECT 
        t.id,
        t.name,
        t.budget,
        COALESCE(SUM(r.amount), 0) as used_amount,
        COALESCE(SUM(pe.estimated_amount), 0) as planned_amount,
        COUNT(DISTINCT r.id) as receipt_count,
        COUNT(DISTINCT pe.id) as planned_count
    FROM teams t
    LEFT JOIN receipts r ON t.id = r.team_id
    LEFT JOIN planned_expenses pe ON t.id = pe.team_id
    WHERE t.event_id = ?
    GROUP BY t.id, t.name, t.budget
    ORDER BY t.name
');
$stmt->execute([$event_id]);
$teams = $stmt->fetchAll();

// イベント全体の計算
$event_budget = $event['budget'];
$total_team_budget = $event_totals['total_team_budget'] ?: 0;
$total_used_amount = $event_totals['total_used_amount'] ?: 0;
$total_planned_amount = $event_totals['total_planned_amount'] ?: 0;
$event_remaining = $event_budget - $total_used_amount;
$unallocated_budget = $event_budget - $total_team_budget;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予算管理概要 - <?= htmlspecialchars($event['name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/KFロゴ.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/KFロゴ.svg">
    <link rel="manifest" href="assets/manifest.json">
    <meta name="theme-color" content="#4A90E2">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <h1 class="title">📊 予算管理概要</h1>
            
            <div class="breadcrumb">
                <a href="event.php?event_id=<?= $event_id ?>">← <?= htmlspecialchars($event['name']) ?>に戻る</a>
            </div>

            <h2 class="subtitle">🎪 イベント全体概要: <?= htmlspecialchars($event['name']) ?></h2>
            
            <!-- イベント全体の予算情報 -->
            <div class="info-cards">
                <div class="info-card budget">
                    <h3>イベント総予算</h3>
                    <div class="amount">¥<?= number_format($event_budget) ?></div>
                </div>
                <div class="info-card allocated">
                    <h3>チーム配分済み</h3>
                    <div class="amount">¥<?= number_format($total_team_budget) ?></div>
                </div>
                <div class="info-card unallocated">
                    <h3>未配分予算</h3>
                    <div class="amount">¥<?= number_format($unallocated_budget) ?></div>
                </div>
            </div>

            <div class="info-cards">
                <div class="info-card used">
                    <h3>使用済み総額</h3>
                    <div class="amount">¥<?= number_format($total_used_amount) ?></div>
                </div>
                <div class="info-card planned">
                    <h3>使用予定総額</h3>
                    <div class="amount">¥<?= number_format($total_planned_amount) ?></div>
                </div>
                <div class="info-card remaining">
                    <h3>残予算</h3>
                    <div class="amount">¥<?= number_format($event_remaining) ?></div>
                </div>
            </div>

            <!-- 予算使用率バー -->
            <div class="budget-progress-container">
                <h3>予算使用状況</h3>
                <div class="budget-progress-bar">
                    <?php
                    $used_percentage = $event_budget > 0 ? ($total_used_amount / $event_budget) * 100 : 0;
                    $planned_percentage = $event_budget > 0 ? ($total_planned_amount / $event_budget) * 100 : 0;
                    $used_percentage = min($used_percentage, 100);
                    $planned_percentage = min($planned_percentage, 100);
                    ?>
                    <div class="progress-bar-used" style="width: <?= $used_percentage ?>%"></div>
                    <div class="progress-bar-planned" style="width: <?= $planned_percentage ?>%; left: <?= $used_percentage ?>%"></div>
                </div>
                <div class="progress-legend">
                    <span class="legend-used">■ 使用済み (<?= number_format($used_percentage, 1) ?>%)</span>
                    <span class="legend-planned">■ 使用予定 (<?= number_format($planned_percentage, 1) ?>%)</span>
                    <span class="legend-remaining">■ 未使用</span>
                </div>
            </div>

            <h2 class="subtitle">👥 チーム別予算詳細</h2>
            
            <!-- チーム別詳細テーブル -->
            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>チーム名</th>
                            <th>配分予算</th>
                            <th>使用済み</th>
                            <th>使用予定</th>
                            <th>残予算</th>
                            <th>使用率</th>
                            <th>領収書数</th>
                            <th>予定項目数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teams)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #718096;">チームが登録されていません</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($teams as $team): 
                            $team_remaining = $team['budget'] - $team['used_amount'];
                            $team_usage_rate = $team['budget'] > 0 ? ($team['used_amount'] / $team['budget']) * 100 : 0;
                            $usage_class = $team_usage_rate > 90 ? 'danger' : ($team_usage_rate > 70 ? 'warning' : 'safe');
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($team['name']) ?></strong></td>
                            <td><strong>¥<?= number_format($team['budget']) ?></strong></td>
                            <td class="amount-used">¥<?= number_format($team['used_amount']) ?></td>
                            <td class="amount-planned">¥<?= number_format($team['planned_amount']) ?></td>
                            <td class="amount-remaining">¥<?= number_format($team_remaining) ?></td>
                            <td>
                                <span class="usage-rate <?= $usage_class ?>">
                                    <?= number_format($team_usage_rate, 1) ?>%
                                </span>
                            </td>
                            <td><?= $team['receipt_count'] ?>件</td>
                            <td><?= $team['planned_count'] ?>件</td>
                            <td>
                                <a href="team.php?team_id=<?= $team['id'] ?>&event_id=<?= $event_id ?>" class="btn btn-primary btn-sm">詳細</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- アラート表示 -->
            <?php if ($unallocated_budget < 0): ?>
            <div class="alert alert-danger">
                <h4>⚠️ 予算超過アラート</h4>
                <p>チームへの配分予算がイベント総予算を <strong>¥<?= number_format(abs($unallocated_budget)) ?></strong> 超過しています。</p>
            </div>
            <?php endif; ?>

            <?php if ($total_used_amount + $total_planned_amount > $event_budget): ?>
            <div class="alert alert-warning">
                <h4>⚠️ 予算注意</h4>
                <p>使用済み + 使用予定額がイベント総予算を超える可能性があります。</p>
                <p>超過予想額: <strong>¥<?= number_format(($total_used_amount + $total_planned_amount) - $event_budget) ?></strong></p>
            </div>
            <?php endif; ?>

            <!-- 使用率が高いチームのアラート -->
            <?php foreach ($teams as $team): 
                $team_usage_rate = $team['budget'] > 0 ? ($team['used_amount'] / $team['budget']) * 100 : 0;
                if ($team_usage_rate > 90): ?>
            <div class="alert alert-warning">
                <h4>⚠️ チーム予算アラート</h4>
                <p><strong><?= htmlspecialchars($team['name']) ?></strong> の予算使用率が <?= number_format($team_usage_rate, 1) ?>% に達しています。</p>
            </div>
            <?php endif; endforeach; ?>

        </div>
    </div>
</body>
</html>
