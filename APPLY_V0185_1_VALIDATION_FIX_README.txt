TRUSTEPS CMS Lab v0.18.5.1

目的:
- 候補収集ラボで名簿URL未入力のまま「名簿URLを取得してプレビュー」を押した際、405 Method Not Allowed画面へ落ちず、候補収集ラボ画面上に赤字エラーを表示する。
- 手動URLリスト未入力時も同様に画面上の赤字エラーへ戻す。
- POST専用の discovery preview/store/export URL へGETアクセスされた場合は /discovery/lab へ戻す。

DB変更:
- migrationなし
- seederなし

適用後:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
