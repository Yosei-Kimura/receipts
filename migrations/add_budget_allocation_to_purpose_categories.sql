-- Migration: add_budget_allocation to purpose_categories
-- Purpose: 既存 DB に対して `purpose_categories.budget_allocation` を追加し、デフォルトカテゴリ作成トリガを更新、検証用クエリを提供します。
-- IMPORTANT: 実行前に必ずデータベースのフルバックアップを取得してください。

START TRANSACTION;

-- 1) 既存テーブルにカラムを追加（デフォルト 0）
ALTER TABLE `purpose_categories`
  ADD COLUMN `budget_allocation` INT NOT NULL DEFAULT 0 AFTER `sort_order`;

-- 2) 既存行の NULL を防ぐ（念のため）
UPDATE `purpose_categories` SET `budget_allocation` = 0 WHERE `budget_allocation` IS NULL;

-- 3) 既存のデフォルトカテゴリ作成トリガを削除（チーム作成時に自動カテゴリ作成しない）
DROP TRIGGER IF EXISTS `create_default_categories_for_new_team`;

-- 注意: 新しいチームではカテゴリは手動で作成する必要があります。

-- 4) （任意）サーバ側で合計割当がチームの予算を超えないようにするトリガ
-- ホスティング環境によりトリガ作成が制限される場合があります。不要であればこのセクションをスキップしてください。
-- 注意: このトリガは INSERT / UPDATE 両方を扱います。

-- DROP TRIGGER IF EXISTS `enforce_team_budget_on_category_insert`;
-- DROP TRIGGER IF EXISTS `enforce_team_budget_on_category_update`;
--
-- DELIMITER $$
-- CREATE TRIGGER `enforce_team_budget_on_category_insert` BEFORE INSERT ON `purpose_categories` FOR EACH ROW
-- BEGIN
--     DECLARE total_alloc INT;
--     SELECT COALESCE(SUM(budget_allocation), 0) INTO total_alloc FROM purpose_categories WHERE team_id = NEW.team_id;
--     IF (total_alloc + NEW.budget_allocation) > (SELECT COALESCE(budget, 0) FROM teams WHERE id = NEW.team_id) THEN
--         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '合計割当額がチーム予算を超えます';
--     END IF;
-- END$$
--
-- CREATE TRIGGER `enforce_team_budget_on_category_update` BEFORE UPDATE ON `purpose_categories` FOR EACH ROW
-- BEGIN
--     DECLARE total_alloc INT;
--     SELECT COALESCE(SUM(budget_allocation), 0) - COALESCE(OLD.budget_allocation, 0) INTO total_alloc FROM purpose_categories WHERE team_id = NEW.team_id;
--     IF (total_alloc + NEW.budget_allocation) > (SELECT COALESCE(budget, 0) FROM teams WHERE id = NEW.team_id) THEN
--         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '合計割当額がチーム予算を超えます（更新）';
--     END IF;
-- END$$
-- DELIMITER ;

-- 5) 検証クエリ: カラムが追加されていることを確認
SELECT
    TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purpose_categories'
    AND COLUMN_NAME = 'budget_allocation';

-- 6) 検証クエリ: 各チームごとの合計割当と予算の比較
SELECT
    t.id AS team_id,
    t.name AS team_name,
    t.budget AS team_budget,
    COALESCE(SUM(pc.budget_allocation), 0) AS total_allocated
FROM teams t
LEFT JOIN purpose_categories pc ON pc.team_id = t.id
GROUP BY t.id
ORDER BY t.id;

-- 7) 検索: 予算を超過しているチームがあれば検出
SELECT
    t.id AS team_id,
    t.name AS team_name,
    t.budget AS team_budget,
    COALESCE(SUM(pc.budget_allocation), 0) AS total_allocated
FROM teams t
LEFT JOIN purpose_categories pc ON pc.team_id = t.id
GROUP BY t.id
HAVING total_allocated > team_budget;

COMMIT;

-- End of migration
-- 実行上の注意:
--  - 本 SQL は、MySQL 8.0 系で動作することを想定しています。
--  - トリガ作成や SIGNAL を使用する箇所はホスティング環境で制限される場合があります。エラーが出る場合は該当トリガ部分をコメントアウトして再度実行してください。
--  - 実行前に必ず DB のバックアップを取得してください。
