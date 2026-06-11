TRUSTEPS CMS Lab v0.19.0
Directory source crawler strengthened.

Apply from: 0.18.9.12
Migration: no
Seeder: no
Composer: no

After applying:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

What changed:
- Fixes DirectorySourceCrawlerService PHP syntax defect.
- Crawls directory source top page plus one shallow internal layer.
- Scores candidate pages by URL, link text, surrounding context, page title, headings, and body excerpt.
- Adds broader keywords for member/shop/company pages.
- Keeps low-confidence fallback candidates when no high-confidence page is found.
- Does not create companies.
