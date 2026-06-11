TRUSTEPS CMS Lab v0.18.8
全国商工会WEBサーチ専用収集アダプタ

目的:
全国商工会連合会のWEBサーチに都道府県条件をPOSTし、各地域の商工会HPを名簿元候補としてsource_recordsに保存できるようにする。
営業先companyは自動作成しない。

追加画面:
- /directory-sources/shokokai-web-search

追加内容:
- 都道府県選択
- 表示件数 10/20/50
- 最大ページ数指定
- search.phpへのPOST検索
- ATOPAGEフォームによるページ送り
- li単位で商工会名 / URL / 郵便番号 / 住所 / TEL / FAX / 商工会コードを抽出
- 壊れURLは保存不可としてURL要確認に表示
- 既存source_recordsとのURL/ドメイン重複確認
- 選択分をsource_recordsへ保存

適用後:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

migrationなし。
seederなし。
composer更新なし。
