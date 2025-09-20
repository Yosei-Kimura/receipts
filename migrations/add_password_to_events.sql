-- イベントテーブルにパスワード機能を追加
-- 実行日: 2025年9月20日

-- eventsテーブルにpassword_hashカラムを追加
ALTER TABLE `events` 
ADD COLUMN `password_hash` VARCHAR(255) NULL DEFAULT NULL COMMENT 'イベント削除時のパスワード（ハッシュ化）';

-- 既存のイベントにデフォルトパスワードを設定（password123をハッシュ化）
-- 本番環境では適切な初期パスワードに変更してください
UPDATE `events` 
SET `password_hash` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE `password_hash` IS NULL;

-- password_hashカラムをNOT NULLに変更
ALTER TABLE `events` 
MODIFY COLUMN `password_hash` VARCHAR(255) NOT NULL COMMENT 'イベント削除時のパスワード（ハッシュ化）';