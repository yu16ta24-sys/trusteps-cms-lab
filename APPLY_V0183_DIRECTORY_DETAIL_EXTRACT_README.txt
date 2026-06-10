TRUSTEPS CMS Lab v0.18.3
候補収集ラボ：名簿詳細ページ1階層掘り対応

この更新の目的
----
v0.18.2の名簿URLリンク抽出に加えて、商工会・自治体・業界団体の名簿で「一覧ページには会社HPがなく、会員詳細ページに入って初めて公式HPが載っている」パターンに対応します。

追加される主な機能
----
- /discovery/lab の名簿URL抽出フォームに「事業者詳細ページを1階層だけ掘る」オプションを追加
- 一覧ページ内の内部リンクから、事業者詳細ページっぽいものを directory_detail_candidate として抽出
- 詳細掘り下げONの場合、詳細ページ候補を最大件数まで低頻度取得
- 詳細ページ内の外部リンクを公式HP候補として抽出
- 詳細ページ由来の候補には「詳細ページ由来」バッジとdetail_page_urlを表示
- 詳細候補件数、詳細ページ取得件数、詳細内外部リンク件数をプレビューに表示
- source_recordsのraw_jsonに detail_page_url / detail_page_title / detail_stats を保存

今回あえて入れていないもの
----
- Googleマップスクレイピング
- Places API
- Web検索API
- Official Site Resolverの本格実装
- HP解析
- Playwright
- company自動作成
- 自動営業判断 / 自動メール送信
- migration / seeder

安全設計メモ
----
詳細ページ取得は1階層だけです。無限クロールはしません。
詳細ページ取得上限はconfig/discovery.phpのdirectory_detail_page_limitで制限します。
外部リンクはURL抽出のみで、公式HPの中身までは取得しません。
抽出結果は必ずプレビュー確認してからsource_recordsへ保存してください。

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
