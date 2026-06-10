TRUSTEPS CMS Lab v0.18.3.4

Purpose:
- Safe rebuild of the preview grouping feature after v0.18.3.2 Blade failure.
- Base/from_version: v0.18.3.1.

Changes:
- Discovery Lab preview rows are grouped into panels:
  official / builder / sns / ec / portal / map / pdf / other.
- Each panel has bulk check and uncheck buttons.
- Manual URL import panel is collapsed by default.
- Directory detail page limit is expanded to 50.

Not included:
- No DB migration.
- No seeder.
- No Google Maps scraping.
- No Places API.
- No Web search API.
- No company auto-create.

Post apply:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

Validation notes before packaging:
- PHP syntax check passed for changed PHP/config files.
- Blade directive balance check passed for discovery/lab.blade.php.
- Manifest from_version/version checked.
