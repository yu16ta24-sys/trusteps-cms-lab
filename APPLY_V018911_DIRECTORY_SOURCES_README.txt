TRUSTEPS CMS Lab v0.18.9.11
From: 0.18.9.7
To: 0.18.9.11

This is an ASCII-manifest rebuild of the directory source management and crawler foundation.

After applying the package, run:
  php artisan migrate --force
  php artisan route:clear
  php artisan view:clear
  php artisan config:clear
  php artisan cache:clear
