TRUSTEPS CMS Lab - Release Tools Diff v0.8.5

追加・更新ファイル:
- VERSION
- deploy.sh
- tools/release_trusteps_cms_lab.bat
- tools/README_RELEASE.txt

削除ファイル:
- なし

目的:
Phase0-8.5: ZIP投入式リリース運用をCMS Lab用に追加します。

今回できること:
- 今後の更新ZIPをbatへドラッグ＆ドロップして、ローカル反映・GitHub push・VPS deployまで進める
- サーバー側deployでDBバックアップ、バックアップ検証、migrate、cache更新、失敗時診断を行う
- .env / vendor / node_modules / storage/logs / bootstrap/cacheなど危険ファイルを拒否する

初回だけ注意:
まだサーバー側にdeploy.shがないため、この差分は通常どおり手動でローカルrepoへ上書きし、git commit/pushしてください。
その後、tools/release_trusteps_cms_lab.bat をZIPなしで起動し、Server deploy onlyを実行してください。

適用手順:
1. ZIPを解凍
2. 中身を C:\Users\ut\Desktop\trusteps-cms-lab に上書き
3. PowerShellで以下

git status
git add .
git commit -m "Add release and deploy tools"
git push

4. tools\release_trusteps_cms_lab.bat をダブルクリック
5. ZIPなし起動なので mode=2 Server deploy only
6. Yで実行

成功後:
今後の差分ZIPは UPDATE_MANIFEST.json と .trusteps-cms-lab-update 必須形式に切り替えます。
