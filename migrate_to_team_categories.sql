-- チーム固有カテゴリへの変更SQL
-- purpose_categoriesテーブルにteam_idカラムを追加

ALTER TABLE `purpose_categories` ADD COLUMN `team_id` int NOT NULL AFTER `id`;

-- 外部キー制約を追加
ALTER TABLE `purpose_categories` ADD KEY `team_id` (`team_id`);
ALTER TABLE `purpose_categories` ADD CONSTRAINT `purpose_categories_ibfk_1` 
  FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

-- ユニーク制約を変更（チーム内でのカテゴリ名の重複を防ぐ）
ALTER TABLE `purpose_categories` DROP INDEX `name`;
ALTER TABLE `purpose_categories` ADD UNIQUE KEY `team_name_unique` (`team_id`, `name`);

-- 既存の全てのチームに対してデフォルトカテゴリを作成するプロシージャ
DELIMITER //
CREATE PROCEDURE CreateDefaultCategoriesForAllTeams()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE current_team_id INT;
    DECLARE cur CURSOR FOR SELECT id FROM teams;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- 既存のグローバルカテゴリを削除
    DELETE FROM purpose_categories WHERE team_id IS NULL OR team_id = 0;

    OPEN cur;
    
    team_loop: LOOP
        FETCH cur INTO current_team_id;
        IF done THEN
            LEAVE team_loop;
        END IF;
        
        -- 各チームに基本カテゴリを作成
        INSERT INTO `purpose_categories` (`team_id`, `name`, `description`, `color`, `sort_order`) VALUES
        (current_team_id, '食材・飲料', '食材、飲み物、お菓子など', '#FF6B6B', 1),
        (current_team_id, '装飾・展示', '装飾用品、展示物、看板など', '#4ECDC4', 2),
        (current_team_id, '設備・機材', '音響機材、照明、テント、机椅子など', '#45B7D1', 3),
        (current_team_id, '衣装・道具', '衣装、小道具、メイク用品など', '#96CEB4', 4),
        (current_team_id, '印刷物', 'ポスター、チラシ、パンフレット、プログラムなど', '#FFEAA7', 5),
        (current_team_id, '交通費', '電車代、バス代、タクシー代、ガソリン代など', '#DDA0DD', 6),
        (current_team_id, 'その他', '上記に当てはまらないもの', '#A0A0A0', 99);
        
    END LOOP;
    
    CLOSE cur;
END//
DELIMITER ;

-- プロシージャを実行
CALL CreateDefaultCategoriesForAllTeams();

-- プロシージャを削除（一度だけ実行するため）
DROP PROCEDURE CreateDefaultCategoriesForAllTeams;