TRUSTEPS CMS Lab v0.18.3.5 Emergency Rollback

Purpose:
- Restore the Discovery Lab implementation that was stable at v0.18.3.1.
- This is a rollback package for environments currently marked as VERSION 0.18.3.4.

Version:
- from_version: 0.18.3.4
- to_version: 0.18.3.5

DB:
- No migration.
- No seeder.

Restored files:
- config/discovery.php
- app/Http/Controllers/DiscoveryLabController.php
- app/Services/Discovery/DirectoryLinkExtractor.php
- resources/views/discovery/lab.blade.php

After applying:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

Note:
- The version number advances to 0.18.3.5 to keep the Release Launcher version chain valid.
- Functional content is the last known stable v0.18.3.1 Discovery Lab behavior.
