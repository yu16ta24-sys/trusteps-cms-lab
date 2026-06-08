TRUSTEPS CMS Lab - Kill Flags Diff v0.9

追加・更新ファイル:
- app/Models/CompanyKillFlag.php
- app/Models/Company.php
- app/Http/Controllers/CompanyController.php
- routes/web.php
- resources/views/companies/show.blade.php

削除ファイル:
- なし

目的:
Phase0-8: kill_flagsの最低限入口を追加します。

今回できること:
- company詳細からkill_flagを手動付与
- company_kill_flagsへ保存
- flag / note / source=manual / flagged_by / flagged_at を記録
- kill_flagが1つでもあれば company.is_killed=true
- kill_flag解除
- 残りkill_flagが0件なら company.is_killed=false

kill_flags v1:
- no_official_site
- defunct
- chain_no_edit_rights
- out_of_scope_size
- compliance_risk

まだやらないこと:
- 自動kill判定
- suppression list
- kill理由マスタ化
- 一括kill
- killされた企業の一覧専用画面

適用方法:
1. このZIPを解凍
2. 中身を C:\Users\ut\Desktop\trusteps-cms-lab に上書き
3. PowerShellで以下を実行

php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan serve

確認手順:
1. company詳細を開く
2. kill_flagを1つ追加する
3. is_killed=trueになるか確認
4. kill_flag一覧に表示されるか確認
5. 解除する
6. is_killed=falseに戻るか確認

動作確認後:
git status
git add .
git commit -m "Add manual company kill flags"
git push
