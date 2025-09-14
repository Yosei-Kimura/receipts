<?php
// Slack通知用関数
function post_receipt_to_slack($event, $team, $amount, $person_name = '') {
    // DB接続
    require __DIR__ . '/config/db_connect.php';
    // チームの使用総額
    $stmt = $pdo->prepare('SELECT SUM(amount) as total FROM receipts WHERE team_id = ?');
    $stmt->execute([$team['id']]);
    $used_total = $stmt->fetchColumn() ?: 0;
    $budget_left = $team['budget'] - $used_total;

    $message = "【領収書登録】\n"
        . "イベント: " . $event['name'] . "\n"
        . "チーム: " . $team['name'] . "\n"
        . ($person_name ? "個人名: " . $person_name . "\n" : "")
        . "今回登録金額: " . $amount . "円\n"
        . "チーム予算: " . $team['budget'] . "円\n"
        . "チーム使用総額: " . $used_total . "円\n"
        . "チーム予算残り: " . $budget_left . "円";

    $payload = json_encode(["text" => $message], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($event['slack_channel_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    // 必要に応じて$resultでエラー処理
}
