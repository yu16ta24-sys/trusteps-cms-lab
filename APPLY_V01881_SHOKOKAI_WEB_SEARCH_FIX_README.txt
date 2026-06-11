TRUSTEPS CMS Lab v0.18.8.1

Fixes the first-page POST flow for the National Shokokai Web Search adapter.

- Adds multiple first-page POST payload attempts.
- Adds fallback via zyokensentaku.php when search.php returns a condition page instead of results.
- Improves stop reasons when no rows are extracted.
- Aligns display count options with the official site: 10 / 50 / 100.
- No migration.
- No seeder.

Post-apply commands:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
