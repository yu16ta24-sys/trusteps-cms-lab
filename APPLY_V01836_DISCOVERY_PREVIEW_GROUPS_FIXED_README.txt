TRUSTEPS CMS Lab v0.18.3.6

From: 0.18.3.5
To:   0.18.3.6

Purpose:
- Re-apply Discovery Lab preview UI improvements after emergency rollback.
- Base implementation is rebuilt from stable v0.18.3.1, not from the broken v0.18.3.2/v0.18.3.4 Blade.

Changes:
- Group preview candidates into frames: official, builder, SNS, EC, portal, Map, PDF, other.
- Add group-level check/uncheck buttons.
- Keep manual URL import section collapsed by default.
- Raise directory detail page limit to 50.

Database:
- No migration.
- No seeder.

After apply:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

