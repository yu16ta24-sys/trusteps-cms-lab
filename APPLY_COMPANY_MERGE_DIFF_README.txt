TRUSTEPS CMS Lab - Company Merge Diff v0.8

追加・更新ファイル:
- database/migrations/2026_06_08_000001_add_merge_previous_status_to_companies_table.php
- app/Models/Company.php
- app/Http/Controllers/CompanyController.php
- routes/web.php
- resources/views/companies/index.blade.php
- resources/views/companies/show.blade.php
- resources/views/companies/merge.blade.php

削除ファイル:
- なし

目的:
Phase0-7: company統合・Undoの最低限入口を追加します。

今回できること:
- company詳細から「このcompanyを統合」
- 統合先company検索
- 手動で company A を company B へ統合
- status=merged / merged_into_id / merged_at / merged_by / merge_reason を記録
- merge_previous_status を追加し、Undo時に元statusへ戻す
- 統合済みcompanyの詳細で統合先を表示
- 統合先companyの詳細で統合されたcompanyを表示
- 統合Undo

設計注意:
- company_source_links は書き換えません
- source_record は元のcompanyに紐づいたままです
- 読み取り時に merged_into_id をたどる設計へ後で拡張します
- まだ自動統合はしません

適用方法:
1. このZIPを解凍
2. 中身を C:\Users\ut\Desktop\trusteps-cms-lab に上書き
3. PowerShellで以下を実行

php artisan migrate
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan serve

確認手順:
1. source_recordsで新しいテストデータを1件作る
2. そのsource_recordから新規companyを作る
3. その新規company詳細で「このcompanyを統合」を押す
4. 統合先に既存の「テスト工務店」を選ぶ
5. 統合元companyのstatusがmergedになるか確認
6. 統合先company詳細で「このcompanyに統合されたcompany」が出るか確認
7. 統合元companyで「統合をUndo」を押す
8. 元statusに戻るか確認

動作確認後:
git status
git add .
git commit -m "Add manual company merge and undo"
git push
