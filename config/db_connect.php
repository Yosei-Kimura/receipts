<?php
// エラー表示（開発時のみ。公開時は削除）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB接続を集中管理するファイル
// 必要に応じて環境変数や .env から読み込むように変更してください
$db_host = 'mysql327.phy.lolipop.lan';
$db_name = 'LAA0956269-events';
$db_user = 'LAA0956269';
$db_pass = 'marie2011';
$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // 本番では詳細を表示せずログに出すなどに変更してください
    die('DB接続失敗: ' . $e->getMessage());
}
