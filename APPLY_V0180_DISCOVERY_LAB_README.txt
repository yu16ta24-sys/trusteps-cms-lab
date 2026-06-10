TRUSTEPS CMS Lab v0.18.0
Seed Collector / 候補収集ラボ（手動URLリスト投入版）

この更新の目的
----
Googleマップや外部HTTP取得に頼らず、手動で集めたURLリストを安全に分類・プレビューし、source_recordsへ投入する入口を追加します。

追加される主な機能
----
- /discovery/lab 新規画面
- 複数URLのテキストエリア投入
- URL文字列だけの分類（HTTP取得なし）
- URL分類：official_site_candidate / portal_candidate / sns_candidate / builder_site_candidate / pdf_candidate / map_candidate / ec_candidate / unknown
- 既存source_recordsとの重複警告
- high-fanout警告（保存ブロックではなく警告のみ）
- 保存前プレビュー必須
- CSV出力（既存source_records CSV取り込み互換フォーマット）
- 選択分をsource_recordsへ保存

今回あえて入れていないもの
----
- migration / seeder
- 業界スコアの箱
- 名簿URLからのaタグ抽出
- HTTP取得
- Googleマップスクレイピング
- Places API
- Web検索API
- Official Site Resolver
- HP解析保存
- company自動作成
- 自動営業判断 / 自動メール送信

適用後の操作
----
Release Launcherで適用後、必要なら以下を実行してください。

php artisan optimize:clear

migrationはありません。
composer installも不要です。

確認URL
----
/discovery/lab

安全設計メモ
----
今回のv0.18.0は、過去の500エラー再発を避けるため、既存のsource_records一覧・company詳細・Dashboardには重い処理を入れていません。
グローバルナビに「候補収集ラボ」リンクを1つ追加し、新規Controller / Service / Bladeで独立させています。
