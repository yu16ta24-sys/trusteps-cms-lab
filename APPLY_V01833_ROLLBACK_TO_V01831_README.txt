TRUSTEPS CMS Lab v0.18.3.3 Emergency Rollback

Purpose:
- Restore the stable v0.18.3.1 Discovery Lab implementation after v0.18.3.2 caused a 500 error on the Discovery Lab page.
- This package is a forward-version rollback: from 0.18.3.2 to 0.18.3.3, while restoring v0.18.3.1 stable files.

Database:
- No migration required.
- No seeder required.

After applying, run on the server:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

Expected result:
- Discovery Lab should return to the stable v0.18.3.1 behavior.
- Candidate grouping UI from v0.18.3.2 is removed until fixed and re-tested.
