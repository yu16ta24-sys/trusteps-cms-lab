TRUSTEPS CMS Lab v0.18.4
MVP data reset screen

from_version: 0.18.3.6
version: 0.18.4
migration: no
seeder: no

What changed:
- Added /system/reset-mvp-data
- Added nav item: MVPリセット
- Flow:
  1. Open reset screen
  2. Press count preview button
  3. Counts are displayed: 本当に良い？
  4. Final confirmation: マジで良い？
  5. Executes reset only after final checkbox + button

Reset target:
- source_records
- companies
- company_source_links
- resolution_decisions
- domains
- hp_snapshots
- hp_facts
- snapshot_update_targets
- company_scores
- company_kill_flags
- judgments
- judgment_reason_links

Kept:
- users
- password/session/cache/job tables
- migrations table
- prefectures / municipalities
- industries / update_targets / reason_codes
- industry_score_axes / industry_scores

Post-apply commands:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
