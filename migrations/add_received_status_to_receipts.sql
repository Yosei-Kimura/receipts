-- Migration: add_received_status_to_receipts
-- Purpose: 領収書に受け取りチェック機能を追加するため、`is_received` カラムを追加します。
-- IMPORTANT: 実行前に必ずデータベースのフルバックアップを取得してください。

START TRANSACTION;

-- 1) receipts テーブルに受け取り状況カラムを追加
ALTER TABLE `receipts`
  ADD COLUMN `is_received` TINYINT(1) NOT NULL DEFAULT 0 AFTER `image_path`;

-- 2) 既存の領収書はすべて未受け取りに設定（念のため）
UPDATE `receipts` SET `is_received` = 0 WHERE `is_received` IS NULL;

-- 3) インデックスを追加（受け取り状況での絞り込み用）
ALTER TABLE `receipts` ADD INDEX `is_received` (`is_received`);

-- 4) 検証クエリ: カラムが追加されていることを確認
SELECT
    TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'receipts'
    AND COLUMN_NAME = 'is_received';

-- 5) 検証クエリ: 既存データの状況確認
SELECT
    COUNT(*) as total_receipts,
    SUM(is_received) as received_receipts,
    COUNT(*) - SUM(is_received) as pending_receipts
FROM receipts;

COMMIT;

-- End of migration
-- 実行後の注意:
--  - is_received = 0: 未受け取り（デフォルト）
--  - is_received = 1: 受け取り済み
--  - team.php で受け取り状況の切り替え機能を実装する必要があります。