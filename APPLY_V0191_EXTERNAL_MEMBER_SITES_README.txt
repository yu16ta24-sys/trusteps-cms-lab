TRUSTEPS CMS Lab v0.19.1

Purpose:
- Fix the directory source exploration flow so it does not stop at same-site pages only.
- Extract external-domain member/business website candidates from discovered member-list pages.
- Add a route and UI to move selected external candidates into source_records.

After applying:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

No migration is required.
