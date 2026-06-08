TRUSTEPS CMS Lab - Migration Diff v0.3

修正ファイル:
- database/migrations/2026_06_08_000000_create_research_mvp_tables.php

削除ファイル:
- なし

修正内容:
- MySQLのインデックス名64文字制限に引っかかっていたため、snapshot_update_targets の複合インデックス名を短縮
- 変更前: Laravel自動生成の長いindex名
- 変更後: sut_target_present_stopped_idx

注意:
前回の migration は途中で失敗しているため、DBに一部テーブルが残っている可能性があります。
この修正版を上書きしたあと、今回は migrate:fresh で作り直してください。

適用手順:
1. このZIPを解凍
2. 中身を C:\Users\ut\Desktop\trusteps-cms-lab に上書き
3. PowerShellで以下を実行

php artisan migrate:fresh
php artisan app:create-admin admin@example.com "password123" --name="YUTA"
php artisan migrate:status

補足:
migrate:fresh はDBテーブルを作り直すため、作成済みの管理ユーザーは消えます。
そのため、直後に app:create-admin を再実行します。
