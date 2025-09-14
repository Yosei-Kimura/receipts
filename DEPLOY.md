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
