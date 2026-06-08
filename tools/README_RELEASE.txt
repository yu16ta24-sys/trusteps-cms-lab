TRUSTEPS CMS Lab ZIP投入式リリース運用 v0.1

目的
----
今後の更新を、差分ZIPを tools/release_trusteps_cms_lab.bat にドラッグ＆ドロップするだけで、
ローカル反映 → GitHub push → VPS deploy まで進めるための運用セットです。

初回だけやること
----
1. この差分ZIPをローカルrepoへ手動で上書きする
   C:\Users\ut\Desktop\trusteps-cms-lab

2. PowerShellでローカルrepoへ移動
   cd C:\Users\ut\Desktop\trusteps-cms-lab

3. GitHubへ保存
   git status
   git add .
   git commit -m "Add release and deploy tools"
   git push

4. tools\release_trusteps_cms_lab.bat をダブルクリック
   ZIPなしで起動すると「Server deploy only」モードになります。
   mode 2 を選び、Yで実行してください。

5. サーバー側へ deploy.sh / VERSION が反映されれば初回セットアップ完了です。

今後の更新ZIPの必須構成
----
UPDATE_MANIFEST.json
.trusteps-cms-lab-update
app/...
routes/...
resources/...
database/...
VERSION

UPDATE_MANIFEST.json 例
----
{
  "app_id": "trusteps-cms-lab",
  "from_version": "0.8.5",
  "version": "0.9.0",
  "summary": "Add company score manual input",
  "requires_migration": false,
  "requires_composer": false,
  "migration_destructive": false,
  "requires_claude_audit": false,
  "delete": []
}

.trusteps-cms-lab-update の中身
----
TRUSTEPS_CMS_LAB_UPDATE_PACKAGE

禁止ファイル
----
.env
.git
vendor
node_modules
storage/logs
bootstrap/cache

サーバー側 deploy.sh の主な処理
----
- 二重起動防止
- メンテナンスモード
- DBバックアップ
- DBバックアップ検証
- GitHub最新化
- composer install（composer変更時のみ）
- migrate --force
- Laravel cache再構築
- 失敗時のgit rollback / DB復元 / 診断ファイル作成

失敗したら
----
サーバー上に診断ファイルが作られます。

/var/www/trusteps-cms-lab/storage/app/deploy_diagnostics/

そのパス、または中身をジッピーに渡してください。
.env、DB_PASSWORD、APP_KEY、SSH秘密鍵は絶対に貼らないでください。
