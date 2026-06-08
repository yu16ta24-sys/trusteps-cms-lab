TRUSTEPS CMS Lab - Login Diff v0.1

このZIPは、Laravel初期状態に最小ログイン機能を追加する差分ZIPです。

上書き先:
C:\Users\ut\Desktop\trusteps-cms-lab

追加/更新ファイル:
- routes/web.php
- routes/console.php
- app/Http/Controllers/Auth/LoginController.php
- app/Http/Controllers/DashboardController.php
- resources/views/layouts/app.blade.php
- resources/views/auth/login.blade.php
- resources/views/dashboard.blade.php

削除ファイル: なし

適用後に実行:
php artisan route:clear
php artisan config:clear
php artisan app:create-admin admin@example.com "password123" --name="YUTA"
php artisan serve

ログインURL:
http://127.0.0.1:8000/login
