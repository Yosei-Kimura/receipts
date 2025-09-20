-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: mysql327.phy.lolipop.lan
-- 生成日時: 2025 年 9 月 20 日 12:02
-- サーバのバージョン： 8.0.35
-- PHP のバージョン: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `LAA0956269-events`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `events`
--

CREATE TABLE `events` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `budget` int NOT NULL,
  `slack_channel_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `events`
--

INSERT INTO `events` (`id`, `name`, `budget`, `slack_channel_url`) VALUES
(4, 'ハロウィン2025', 699200, 'https://hooks.slack.com/services/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');

-- --------------------------------------------------------

--
-- テーブルの構造 `planned_expenses`
--

CREATE TABLE `planned_expenses` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `purpose_category_id` int DEFAULT NULL,
  `estimated_amount` int NOT NULL,
  `person_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `purpose_categories`
--

CREATE TABLE `purpose_categories` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '#4A90E2',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `budget_allocation` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `purpose_categories`
--

INSERT INTO `purpose_categories` (`id`, `team_id`, `name`, `description`, `color`, `is_active`, `sort_order`, `budget_allocation`, `created_at`, `updated_at`) VALUES
(15, 7, '仮装コンテスト賞品・参加賞', '', '#4a90e2', 1, 0, 0, '2025-09-19 19:40:23', '2025-09-19 19:40:23'),
(16, 7, '出演料', '', '#e24bb2', 1, 0, 0, '2025-09-19 19:41:20', '2025-09-19 19:41:20'),
(17, 7, 'ビンゴ大会景品', '', '#e2784b', 1, 0, 50000, '2025-09-19 19:41:38', '2025-09-20 09:13:05'),
(60, 13, 'お化け屋敷', '', '#4a90e2', 1, 0, 30000, '2025-09-20 11:48:59', '2025-09-20 11:48:59');

-- --------------------------------------------------------

--
-- テーブルの構造 `receipts`
--

CREATE TABLE `receipts` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `person_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `purpose_category_id` int DEFAULT NULL,
  `amount` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `receipts`
--

INSERT INTO `receipts` (`id`, `team_id`, `person_name`, `purpose`, `purpose_category_id`, `amount`, `image_path`, `created_at`) VALUES
(13, 7, '木村', '出演料', NULL, 60000, '', '2025-09-15 13:44:49'),
(14, 7, '甲斐田愛子', '出演者', NULL, 1000, '', '2025-09-17 17:49:46'),
(15, 7, 'あ', 'あ', NULL, 100, '', '2025-09-17 18:11:18');

-- --------------------------------------------------------

--
-- テーブルの構造 `teams`
--

CREATE TABLE `teams` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `budget` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `teams`
--

INSERT INTO `teams` (`id`, `event_id`, `name`, `budget`) VALUES
(7, 4, 'ステージ', 181000),
(8, 4, '全体', 178200),
(9, 4, '広報', 20000),
(10, 4, 'ゲーム', 50000),
(11, 4, 'パレード', 60000),
(12, 4, 'TOT', 40000),
(13, 4, 'お化け屋敷', 30000);

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `planned_expenses`
--
ALTER TABLE `planned_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `purpose_category_id` (`purpose_category_id`);

--
-- テーブルのインデックス `purpose_categories`
--
ALTER TABLE `purpose_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_name_unique` (`team_id`,`name`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `sort_order` (`sort_order`),
  ADD KEY `team_id` (`team_id`);

--
-- テーブルのインデックス `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `purpose_category_id` (`purpose_category_id`);

--
-- テーブルのインデックス `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `events`
--
ALTER TABLE `events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- テーブルの AUTO_INCREMENT `planned_expenses`
--
ALTER TABLE `planned_expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `purpose_categories`
--
ALTER TABLE `purpose_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- テーブルの AUTO_INCREMENT `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- テーブルの AUTO_INCREMENT `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `planned_expenses`
--
ALTER TABLE `planned_expenses`
  ADD CONSTRAINT `planned_expenses_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `planned_expenses_ibfk_2` FOREIGN KEY (`purpose_category_id`) REFERENCES `purpose_categories` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `purpose_categories`
--
ALTER TABLE `purpose_categories`
  ADD CONSTRAINT `purpose_categories_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`purpose_category_id`) REFERENCES `purpose_categories` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
