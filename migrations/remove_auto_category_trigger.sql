-- Migration: remove_auto_category_trigger
-- Purpose: チーム作成時にデフォルトカテゴリを自動作成するトリガーを削除します。
-- 新しいチームではカテゴリは手動で作成する必要があります。
-- IMPORTANT: 実行前に必ずデータベースのフルバックアップを取得してください。

START TRANSACTION;

-- 1) 既存のデフォルトカテゴリ作成トリガを削除
DROP TRIGGER IF EXISTS `create_default_categories_for_new_team`;

-- 2) 検証クエリ: トリガーが削除されていることを確認
SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE 
FROM information_schema.TRIGGERS 
WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'create_default_categories_for_new_team';

-- 3) 結果の説明:
-- 上記クエリで結果が0行の場合、トリガーは正常に削除されています。
-- 1行以上返される場合、トリガーがまだ存在しています。

COMMIT;

-- End of migration
-- 実行後の注意:
--  - 新しく作成されるチームには自動でカテゴリが作成されません。
--  - 必要に応じて、各チームで手動でカテゴリを追加してください。
--  - purpose_categories.php の管理画面から新しいカテゴリを追加できます。