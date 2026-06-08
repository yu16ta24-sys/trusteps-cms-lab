TRUSTEPS CMS Lab - Seeder Diff v0.4

追加・更新ファイル:
- database/seeders/DatabaseSeeder.php
- database/seeders/PrefectureSeeder.php
- database/seeders/MunicipalitySeeder.php
- database/seeders/IndustrySeeder.php
- database/seeders/UpdateTargetSeeder.php
- database/seeders/ReasonCodeSeeder.php

削除ファイル:
- なし

目的:
Phase0-3: 研究MVPの初期マスターをDBに投入します。

投入内容:
- prefectures: 47都道府県 + TRUSTEPS独自prefecture_scale
- municipalities: Phase1前半検証県（長野・愛媛・福井）の主要市のみ先行投入
- industries: 業種マスタ19種
- update_targets: 更新対象17種
- reason_codes: send_reason / no_reason / hold_reason

適用方法:
1. このZIPを解凍
2. 中身を C:\Users\ut\Desktop\trusteps-cms-lab に上書き
3. PowerShellで以下を実行

php artisan db:seed

確認用:
mysql -u root -e "SELECT COUNT(*) AS prefectures FROM trusteps_cms_lab.prefectures; SELECT COUNT(*) AS municipalities FROM trusteps_cms_lab.municipalities; SELECT COUNT(*) AS industries FROM trusteps_cms_lab.industries; SELECT COUNT(*) AS update_targets FROM trusteps_cms_lab.update_targets; SELECT type, COUNT(*) AS count FROM trusteps_cms_lab.reason_codes GROUP BY type;"

注意:
- updateOrInsert を使っているため、同じSeederを複数回実行しても基本的に重複しません。
- municipalities は最初から全国全件ではなく、長野・愛媛・福井の主要市だけです。
- 全国市区町村マスターは後でCSV投入または専用Seederで拡張します。
