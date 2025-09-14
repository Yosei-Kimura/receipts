-- 領収書テーブルに個人名カラムを追加するSQL
-- 実行日: 2025年9月15日

-- receiptsテーブルにperson_nameカラムを追加
ALTER TABLE `receipts` 
ADD COLUMN `person_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER `team_id`;

-- 既存データに対してデフォルト値を設定（必要に応じて）
-- UPDATE `receipts` SET `person_name` = '未設定' WHERE `person_name` = '';
