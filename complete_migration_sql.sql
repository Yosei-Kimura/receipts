-- ============================================================================
-- チーム固有カテゴリ機能 + 予算配分機能への完全移行SQL
-- 現在のグローバルカテゴリ（ID:1-7）をチーム固有に変更し、予算配分機能を追加
-- ============================================================================

-- Step 1: バックアップの推奨
-- 実行前に必ずデータベースのバックアップを取得してください

-- Step 2: 安全のため外部キー制約を一時的に削除
SET FOREIGN_KEY_CHECKS = 0;

-- Step 3: purpose_categoriesテーブルの構造を変更
-- team_idカラムとbudget_allocationカラムを追加
ALTER TABLE `purpose_categories` 
ADD COLUMN `team_id` int NOT NULL AFTER `id`,
ADD COLUMN `budget_allocation` int DEFAULT 0 AFTER `sort_order`;

-- Step 4: 既存のユニーク制約を削除（チーム固有にするため）
ALTER TABLE `purpose_categories` DROP INDEX `name`;

-- Step 5: 既存のグローバルカテゴリ（ID:1-7）を現在のチーム（ID:7）固有に変更
UPDATE `purpose_categories` SET `team_id` = 7 WHERE `id` IN (1, 2, 3, 4, 5, 6, 7);

-- Step 6: 新しいインデックスと制約を追加
-- team_idにインデックスを追加
ALTER TABLE `purpose_categories` ADD KEY `team_id` (`team_id`);

-- チーム内でのカテゴリ名ユニーク制約を追加
ALTER TABLE `purpose_categories` ADD UNIQUE KEY `team_name_unique` (`team_id`, `name`);

-- Step 7: 外部キー制約を追加
ALTER TABLE `purpose_categories` ADD CONSTRAINT `purpose_categories_ibfk_1` 
  FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

-- Step 8: 外部キー制約を再有効化
SET FOREIGN_KEY_CHECKS = 1;

-- Step 9: 新しいチーム作成時に自動でデフォルトカテゴリを作成するトリガーを作成
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

-- Step 10: 変更内容の確認クエリ
SELECT 
    'チーム固有カテゴリ機能への変更が完了しました。' as status,
    COUNT(*) as total_categories,
    COUNT(DISTINCT team_id) as teams_with_categories
FROM `purpose_categories`;

-- Step 11: 現在のカテゴリ状況を確認
SELECT 
    pc.team_id,
    t.name as team_name,
    pc.name as category_name,
    pc.budget_allocation,
    pc.is_active
FROM `purpose_categories` pc
JOIN `teams` t ON pc.team_id = t.id
ORDER BY pc.team_id, pc.sort_order;

-- ============================================================================
-- 変更内容サマリー:
-- 1. purpose_categoriesテーブルにteam_id（チーム固有化）とbudget_allocation（予算配分）カラムを追加
-- 2. 既存のグローバルカテゴリ（ID:1-7）をチームID:7に関連付け
-- 3. チーム内でのカテゴリ名ユニーク制約に変更
-- 4. 新チーム作成時の自動カテゴリ作成トリガーを追加
-- 5. 適切な外部キー制約とインデックスを設定
-- ============================================================================

-- 注意事項:
-- - 既存の領収書・予定用途データには影響ありません
-- - 現在のチーム「ステージ」（ID:7）のカテゴリはそのまま使用できます
-- - 新しいチームには自動的にデフォルトカテゴリが作成されます
-- - budget_allocationは0でスタート（予算制限なし）