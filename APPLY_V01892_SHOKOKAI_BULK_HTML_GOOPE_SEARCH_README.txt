TRUSTEPS CMS Lab v0.18.9.2

内容:
- 全国商工会HTML一括取込で、r.goope.jp等のグーペ系URLをデフォルトチェックONにする。
- グーペは同一ドメイン配下に複数商工会が存在するため、ドメイン重複だけでは初期チェックOFFにしない。
- URLなし行にGoogle検索補助リンクを表示する。
- Google検索結果の自動スクレイピングはしない。公式HP候補は人間確認を前提にする。

適用後:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
