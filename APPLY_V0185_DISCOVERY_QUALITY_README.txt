TRUSTEPS CMS Lab v0.18.5
Discovery candidate adoption quality update.

From: 0.18.4
To:   0.18.5

Includes:
- Candidate confidence labels: high / medium / low / review / invalid.
- Save recommendation labels and reasons in preview table.
- Official/builder candidates are still the main default selections, but duplicate/high-fanout candidates are now default OFF.
- Directory extraction now returns excluded-link samples for review.
- Preview shows excluded links in a collapsible panel.
- source_records.raw_json now stores candidate_group, confidence label/reason, recommendation, and selected_by_default.

No migration.
No seeder.
No composer update.

After applying:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
