-- 用途カテゴリマスターテーブル作成
CREATE TABLE `purpose_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '#4A90E2',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `is_active` (`is_active`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 基本的な用途カテゴリを挿入
INSERT INTO `purpose_categories` (`name`, `description`, `color`, `sort_order`) VALUES
('食材・飲料', '食材、飲み物、お菓子など', '#FF6B6B', 1),
('装飾・展示', '装飾用品、展示物、看板など', '#4ECDC4', 2),
('設備・機材', '音響機材、照明、テント、机椅子など', '#45B7D1', 3),
('衣装・道具', '衣装、小道具、メイク用品など', '#96CEB4', 4),
('印刷物', 'ポスター、チラシ、パンフレット、プログラムなど', '#FFEAA7', 5),
('交通費', '電車代、バス代、タクシー代、ガソリン代など', '#DDA0DD', 6),
('その他', '上記に当てはまらないもの', '#A0A0A0', 99);

-- receiptsテーブルに用途カテゴリIDを追加
ALTER TABLE `receipts` ADD COLUMN `purpose_category_id` int DEFAULT NULL AFTER `purpose`;
ALTER TABLE `receipts` ADD KEY `purpose_category_id` (`purpose_category_id`);
ALTER TABLE `receipts` ADD CONSTRAINT `receipts_ibfk_2` 
  FOREIGN KEY (`purpose_category_id`) REFERENCES `purpose_categories` (`id`) ON DELETE SET NULL;

-- planned_expensesテーブルにも用途カテゴリIDを追加
ALTER TABLE `planned_expenses` ADD COLUMN `purpose_category_id` int DEFAULT NULL AFTER `purpose`;
ALTER TABLE `planned_expenses` ADD KEY `purpose_category_id` (`purpose_category_id`);
ALTER TABLE `planned_expenses` ADD CONSTRAINT `planned_expenses_ibfk_2` 
  FOREIGN KEY (`purpose_category_id`) REFERENCES `purpose_categories` (`id`) ON DELETE SET NULL;