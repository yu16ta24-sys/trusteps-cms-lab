TRUSTEPS CMS Lab - Companies Diff v0.6

追加・更新ファイル:
- app/Models/Company.php
- app/Models/Domain.php
- app/Models/CompanySourceLink.php
- app/Models/Industry.php
- app/Models/Municipality.php
- app/Models/Prefecture.php
- app/Models/SourceRecord.php
- app/Http/Controllers/CompanyController.php
- app/Http/Controllers/SourceRecordController.php
- routes/web.php
- resources/views/layouts/app.blade.php
- resources/views/dashboard.blade.php
- resources/views/source_records/show.blade.php
- resources/views/companies/index.blade.php
- resources/views/companies/create_from_source.blade.php
- resources/views/companies/show.blade.php

削除ファイル:
- なし

目的:
Phase0-5: companies生成・最低限の手動名寄せ入口を追加します。

今回できること:
- source_record詳細から「このデータからcompany作成」
- companies一覧
- company詳細
- domains作成
- company_source_links作成
- source_recordがcompanyへリンク済みか確認

まだやらないこと:
- 自動名寄せ
- 既存companyへの手動リンク
- company統合/Undo
- kill_flags
- スコアリング
- HPスナップショット

適用方法:
1. このZIPを解凍
2. 中身を C:\Users\ut\Desktop\trusteps-cms-lab に上書き
3. PowerShellで以下を実行

php artisan route:clear
php artisan config:clear
php artisan serve

確認URL:
http://127.0.0.1:8000/source-records

確認手順:
1. source_records詳細を開く
2. 「このデータからcompany作成」を押す
3. 業種などを入れてcompany作成
4. companies詳細に遷移する
5. source linksにsource_recordが紐づいているか確認
6. companies一覧に表示されるか確認

動作確認後:
git status
git add .
git commit -m "Add manual company creation from source records"
git push
