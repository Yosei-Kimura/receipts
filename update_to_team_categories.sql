-- チーム固有カテゴリ機能への変更SQL
-- 現在のグローバルカテゴリをチーム固有に変更するための完全なSQL

-- Step 1: 一時的に外部キー制約を削除
ALTER TABLE `planned_expenses` DROP FOREIGN KEY `planned_expenses_ibfk_2`;
ALTER TABLE `receipts` DROP FOREIGN KEY `receipts_ibfk_2`;

-- Step 2: purpose_categoriesテーブルにteam_idとbudget_allocationカラムを追加
ALTER TABLE `purpose_categories` ADD COLUMN `team_id` int NOT NULL AFTER `id`;
ALTER TABLE `purpose_categories` ADD COLUMN `budget_allocation` int DEFAULT 0 AFTER `sort_order`;

-- Step 3: 既存のユニーク制約を削除
ALTER TABLE `purpose_categories` DROP INDEX `name`;

-- Step 4: 既存の全チームに対して現在のカテゴリをコピー
-- 現在存在するチーム（id=7）に対してカテゴリをコピー
INSERT INTO `purpose_categories` (`team_id`, `name`, `description`, `color`, `is_active`, `sort_order`, `budget_allocation`, `created_at`, `updated_at`)
SELECT 7, `name`, `description`, `color`, `is_active`, `sort_order`, 0, NOW(), NOW()
FROM `purpose_categories` 
WHERE `team_id` = 0;

-- Step 5: 元のグローバルカテゴリを削除
DELETE FROM `purpose_categories` WHERE `team_id` = 0;

-- Step 6: team_idに対するインデックスを追加
ALTER TABLE `purpose_categories` ADD KEY `team_id` (`team_id`);

-- Step 7: チーム内でのカテゴリ名ユニーク制約を追加
ALTER TABLE `purpose_categories` ADD UNIQUE KEY `team_name_unique` (`team_id`, `name`);

-- Step 8: 外部キー制約を追加
ALTER TABLE `purpose_categories` ADD CONSTRAINT `purpose_categories_ibfk_1` 
  FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

-- Step 9: 元の外部キー制約を復活
ALTER TABLE `planned_expenses` ADD CONSTRAINT `planned_expenses_ibfk_2` 
  FOREIGN KEY (`purpose_category_id`) REFERENCES `purpose_categories` (`id`) ON DELETE SET NULL;

ALTER TABLE `receipts` ADD CONSTRAINT `receipts_ibfk_2` 
  FOREIGN KEY (`purpose_category_id`) REFERENCES `purpose_categories` (`id`) ON DELETE SET NULL;

-- Step 10: 新しいチームが作成された時に自動でデフォルトカテゴリを作成するトリガー（オプション）
DELIMITER //
CREATE TRIGGER `create_default_categories_for_new_team` 
AFTER INSERT ON `teams`
FOR EACH ROW
BEGIN
    INSERT INTO `purpose_categories` (`team_id`, `name`, `description`, `color`, `sort_order`, `budget_allocation`) VALUES
    (NEW.id, '食材・飲料', '食材、飲み物、お菓子など', '#FF6B6B', 1, 0),
    (NEW.id, '装飾・展示', '装飾用品、展示物、看板など', '#4ECDC4', 2, 0),
    (NEW.id, '設備・機材', '音響機材、照明、テント、机椅子など', '#45B7D1', 3, 0),
    (NEW.id, '衣装・道具', '衣装、小道具、メイク用品など', '#96CEB4', 4, 0),
    (NEW.id, '印刷物', 'ポスター、チラシ、パンフレット、プログラムなど', '#FFEAA7', 5, 0),
    (NEW.id, '交通費', '電車代、バス代、タクシー代、ガソリン代など', '#DDA0DD', 6, 0),
    (NEW.id, 'その他', '上記に当てはまらないもの', '#A0A0A0', 99, 0);
END//
DELIMITER ;

-- 実行完了のメッセージ
SELECT 'チーム固有カテゴリ機能への変更が完了しました。' as message;