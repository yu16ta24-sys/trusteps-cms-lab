TRUSTEPS CMS Lab v0.18.9.6

source_records一覧のページ送り修正。

変更内容:
- Laravel標準のTailwind想定ページネーションを使わず、source_records専用のコンパクトページネーションへ差し替え。
- 未適用Tailwind環境でSVG矢印が巨大表示される問題を回避。
- DB/migration/seeder変更なし。

適用後:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
