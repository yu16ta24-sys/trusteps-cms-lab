TRUSTEPS CMS Lab v0.18.9.5

内容:
- 全国商工会HTML一括取込で、有効URL行にも「修正URL」入力欄を表示します。
- URLなし/URL要確認/リンク切れ/誤URLの行に、公式HP URLを手入力して差し替え保存できます。
- 手入力URLがある行は、チェック状態に関わらず保存対象になります。
- raw_jsonには manual_url_provided / manual_url / original_status_key / original_status_label を残します。

適用後:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

DB変更: なし
Seeder: なし
