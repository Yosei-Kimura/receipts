# 自動デプロイ設定ガイド

## GitHub Actions を使用した自動デプロイ

このプロジェクトは、GitHub Actionsを使用してロリポップサーバーに自動デプロイされます。

### 設定手順

1. **GitHubリポジトリの作成**
   - GitHubにこのプロジェクトをプッシュ

2. **GitHub Secrets の設定**
   - GitHubリポジトリの Settings > Secrets and variables > Actions に移動
   - 以下のSecretを追加：
     - `FTP_SERVER`: `ssh.lolipop.jp`
     - `FTP_USERNAME`: ロリポップのFTPアカウント名
     - `FTP_PASSWORD`: ロリポップのFTPパスワード

3. **ロリポップサーバー設定確認**
   - FTPアクセスが有効になっていることを確認
   - デプロイ先ディレクトリが存在することを確認

### デプロイの実行

- `main` または `master` ブランチにプッシュすると自動的にデプロイされます
- GitHub Actionsタブでデプロイの状況を確認できます

### デプロイ対象外ファイル

以下のファイル/フォルダはデプロイされません：
- `.git*` 関連
- `node_modules/`
- `.env` ファイル
- `.vscode/`
- `README.md`

## 代替方法：手動FTPアップロード

GitHub Actionsを使用しない場合は、以下の方法も利用できます：

### 方法1: Git Hooks を使用

```bash
# フックスクリプトの作成
echo '#!/bin/sh
rsync -avz --delete ./ username@ssh.lolipop.jp:/home/users/1/bitter.jp-kf-environment/web/領収書管理システム/' > .git/hooks/post-commit
chmod +x .git/hooks/post-commit
```

### 方法2: FTPクライアントの自動化

FileZillaやWinSCPなどのFTPクライアントで同期設定を行う

## トラブルシューティング

- デプロイが失敗する場合は、GitHub Actionsのログでエラーメッセージをチェックしてください
- FTP接続エラーの場合は、ロリポップの管理画面でFTP設定を確認してください

## データベースの更新

### 使用予定用途機能追加時のデータベース更新

使用予定用途機能を追加するために、以下のSQLファイルを実行してデータベーステーブルを更新してください：

1. **receiptsテーブルへの列追加** (`assets/update_receipts.sql`)
```sql
ALTER TABLE `receipts` ADD COLUMN `person_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER `amount`;
```

2. **使用予定用途テーブルの作成** (`assets/add_planned_expenses.sql`)
```sql
CREATE TABLE `planned_expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `team_id` int NOT NULL,
  `purpose` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `estimated_amount` int NOT NULL,
  `person_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `planned_expenses_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### 実行方法

1. ロリポップの管理画面 > データベース > phpMyAdmin にアクセス
2. 対象のデータベースを選択
3. SQLタブを開く
4. 上記のSQLを順番に実行

### 新機能について

- **使用予定用途**: 領収書提出前に予算の使用予定を事前登録できます
- **使用済み機能**: 使用予定用途が実際に使用された場合、ワンクリックで削除できます
- **予算管理強化**: 使用総額と使用予定額が分けて表示され、より詳細な予算管理が可能
