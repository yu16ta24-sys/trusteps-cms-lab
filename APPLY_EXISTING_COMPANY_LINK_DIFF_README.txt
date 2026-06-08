TRUSTEPS CMS Lab - Existing Company Link Diff v0.7

追加・更新ファイル:
- app/Http/Controllers/CompanyController.php
- routes/web.php
- resources/views/source_records/show.blade.php
- resources/views/companies/link_existing_from_source.blade.php

削除ファイル:
- なし

目的:
Phase0-6: source_recordを既存companyへ手動リンクできる入口を追加します。

今回できること:
- source_record詳細から「既存companyへリンク」
- 既存company検索
- 選択したcompanyへ company_source_links を作成
- match_type=manual_same でリンク
- 既にリンク済みのsource_recordは再リンク不可

まだやらないこと:
- 自動名寄せ
- 候補自動生成
- resolution_decisions
- company統合/Undo
- kill_flags
- スコアリング

適用方法:
1. このZIPを解凍
2. 中身を C:\Users\ut\Desktop\trusteps-cms-lab に上書き
3. PowerShellで以下を実行

php artisan route:clear
php artisan config:clear
php artisan serve

確認手順:
1. source_recordsで新しいテストデータをもう1件作る
2. 新しいsource_recordの詳細を開く
3. 「既存companyへリンク」を押す
4. 既存の「テスト工務店」にリンクする
5. company詳細のsource linksが2件になるか確認

動作確認後:
git status
git add .
git commit -m "Add manual link from source records to existing companies"
git push
