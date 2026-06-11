TRUSTEPS CMS Lab v0.18.7
Directory Source Lab / 名簿元収集ラボ

Purpose:
- Collect source pages that can later produce sales candidates.
- This version saves directory-source candidates into source_records only.
- It does not create companies.

Added routes:
- GET  /directory-sources/lab
- POST /directory-sources/lab/preview
- POST /directory-sources/lab/store

Targets:
- 商工会
- 商工会議所
- 中央会・協同組合
- 業界団体・協会
- 生活衛生組合
- 観光協会
- 公的事業所DB
- その他・要確認

No migration / no seeder.

Post apply:
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
