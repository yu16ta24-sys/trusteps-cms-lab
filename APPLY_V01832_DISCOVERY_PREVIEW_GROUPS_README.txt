TRUSTEPS CMS Lab v0.18.3.2

内容:
- 候補収集ラボのプレビュー結果をカテゴリ別の枠に分離
  - 公式候補
  - ビルダー系
  - SNS
  - EC・モール
  - ポータル
  - Map
  - PDF
  - その他・不明
- 各カテゴリ枠に「この枠を全チェック」「この枠を全解除」を追加
- 手動URLリスト取り込み枠をアコーディオン化し、初期状態では閉じる
- 事業者詳細ページの取得上限を最大50件へ拡充

DB変更:
- migrationなし
- seederなし

適用後コマンド:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

注意:
- Googleマップスクレイピング、Places API、Web検索API、HP解析、company自動作成は入れていない。
- 詳細ページ掘り下げは1階層のみ。
