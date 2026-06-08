TRUSTEPS CMS Lab - Source Records Diff v0.5

追加・更新ファイル:
- app/Models/SourceRecord.php
- app/Http/Controllers/SourceRecordController.php
- routes/web.php
- resources/views/layouts/app.blade.php
- resources/views/dashboard.blade.php
- resources/views/source_records/index.blade.php
- resources/views/source_records/create.blade.php
- resources/views/source_records/import.blade.php
- resources/views/source_records/show.blade.php

削除ファイル:
- なし

目的:
Phase0-4: source_records取り込み基盤を追加します。

今回できること:
- source_records 一覧表示
- source_records 手動登録
- CSV取り込み
- raw_json保存
- normalized_domain / normalized_phone / name_norm の最低限生成
- source_record詳細でraw_json確認

まだやらないこと:
- 自動クロール
- 自動名寄せ
- companies自動生成
- HPスナップショット取得
- スコアリング
- 送る/なし/保留判断

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
1. ログインする
2. source_records一覧を開く
3. 手動登録を1件試す
4. 登録後、一覧と詳細でraw_jsonが見えるか確認する
5. 余裕があればCSV取り込みを1回試す

動作確認後:
git status
git add .
git commit -m "Add source records intake screens"
git push
