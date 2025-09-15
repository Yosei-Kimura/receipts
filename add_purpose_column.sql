-- receiptsテーブルにpurpose(用途)カラムを追加
ALTER TABLE receipts ADD COLUMN purpose VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' AFTER person_name;
