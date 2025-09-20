# 用途カテゴリ機能の実装手順

## 1. データベースの更新

### 必要なSQLファイルの実行順序

1. **add_purpose_column.sql** - 既に存在する場合は省略可
   ```sql
   ALTER TABLE receipts ADD COLUMN purpose VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' AFTER person_name;
   ```

2. **create_purpose_categories.sql** - 用途カテゴリ機能の追加
   - purpose_categoriesテーブルの作成
   - 基本カテゴリデータの挿入
   - receiptsとplanned_expensesテーブルにpurpose_category_idカラムの追加

### データベース適用方法

phpMyAdminまたはMySQLクライアントで以下のファイルを順番に実行：

1. `add_purpose_column.sql`（まだ実行していない場合）
2. `create_purpose_categories.sql`

## 2. 新機能の説明

### 用途カテゴリ機能
- 領収書と予定用途を色分けされたカテゴリで管理
- 視覚的にわかりやすいグループ分け
- カテゴリごとの集計表示

### 基本カテゴリ（デフォルト）
- 🍽️ 食材・飲料（赤系）
- 🎨 装飾・展示（青緑系） 
- 🔧 設備・機材（青系）
- 👗 衣装・道具（緑系）
- 📄 印刷物（黄系）
- 🚗 交通費（紫系）
- 📦 その他（グレー系）

### ファイル構成
- `purpose_categories.php` - カテゴリ管理画面
- `create_purpose_categories.sql` - データベース拡張SQL

## 3. 使用方法

1. **カテゴリ管理**: `/purpose_categories.php` でカテゴリの追加・編集・削除
2. **領収書登録**: カテゴリ選択 + 自由記述の用途を両方記録
3. **グループ表示**: チーム画面でカテゴリごとに色分け表示

## 4. データの移行について

既存の領収書データには purpose_category_id が NULL で設定されます。
必要に応じて管理者が後からカテゴリを割り当てることができます。

自由記述の用途（purposeカラム）は今まで通り保持されるため、
データの損失はありません。