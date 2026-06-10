TRUSTEPS CMS Lab v0.18.2
候補収集ラボ：名簿URLリンク抽出

この更新の目的
----
v0.18.0の手動URLリスト投入に加えて、商工会・自治体・業界団体などの名簿ページURLを1件入力し、そのページ内のaタグリンクを候補URLとして抽出できるようにします。

追加される主な機能
----
- /discovery/lab に「名簿URLからリンク抽出」フォームを追加
- 指定した名簿ページURLを1回だけHTTP取得
- robots.txtを簡易確認
- 文字コード自動判定（UTF-8 / Shift-JIS系など）
- aタグのhref・リンクテキスト・周辺テキストを抽出
- 既存のURL分類を流用
- 保存前プレビュー
- CSV出力
- 選択分をsource_recordsへ保存

今回あえて入れていないもの
----
- Googleマップスクレイピング
- Places API
- Web検索API
- Official Site Resolver
- HP解析
- Playwright
- company自動作成
- 自動営業判断 / 自動メール送信
- migration / seeder

安全設計メモ
----
この更新では、Googleマップを候補発見元にしません。
取得するのはユーザーが指定した名簿ページ1件のみで、抽出リンク数はconfig/discovery.phpのdirectory_link_limitで制限します。
外部サイトのHTML構造は不安定なので、抽出結果は必ずプレビュー確認してからsource_recordsへ保存してください。

適用後の操作
----
Release Launcherで適用後、必要なら以下を実行してください。

php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

migrationはありません。
composer installも不要です。

確認URL
----
/discovery/lab
