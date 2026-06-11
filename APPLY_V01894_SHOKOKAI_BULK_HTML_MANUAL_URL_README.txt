TRUSTEPS CMS Lab v0.18.9.4
Shokokai bulk HTML manual URL resolution patch.

from_version: 0.18.9.3
to_version: 0.18.9.4
migration: none
seeder: none
composer: none

Changes:
- Rows without URL or with invalid URL now show a Google search link and a manual official URL input field.
- If a manual URL is entered, the row is saved even without checking the disabled checkbox.
- Manual URLs accept full http(s) URLs or bare domains; bare domains are normalized to https://.
- Saved source_records include manual_url metadata and original no_url/invalid status.
- Existing source_records with the same pref_code + shokokai_code are excluded from future previews, so saved manual-resolution rows do not keep reappearing.

Post apply:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
