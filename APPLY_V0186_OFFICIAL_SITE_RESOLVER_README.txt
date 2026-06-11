TRUSTEPS CMS Lab v0.18.6
Official Site Resolver MVP

from_version: 0.18.5.1
to_version: 0.18.6
migration: none
seeder: none
composer: none

追加内容:
- /resolver/official-sites を追加
- 手動URLリストをHTTP取得してプレビュー
- title / meta description / generator / canonical / og:site_name を取得
- SSL有無を確認
- WordPress推定（wp-content/wp-includes/generator）
- 簡易ビルダー推定
- 問い合わせフォーム / メール / 電話の簡易検出
- 信頼度・保存推奨理由を表示
- 選択分を source_records に保存
- company自動作成はしない

適用後コマンド:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

注意:
外部サイトへHTTPアクセスするため、一度の解析件数は標準30件に制限。
