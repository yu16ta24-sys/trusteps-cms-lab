TRUSTEPS CMS Lab - Global Navigation v0.11.1

追加・更新ファイル:
- UPDATE_MANIFEST.json
- .trusteps-cms-lab-update
- VERSION
- resources/views/layouts/app.blade.php

削除ファイル:
- なし

目的:
画面間の導線が個別ボタン中心で、全体移動の上メニューがなかったため、
全ページ共通のグローバルナビゲーションを追加します。

追加メニュー:
- Dashboard
- source_records
- companies
- 営業候補

変更内容:
- 上部メニューを追加
- 現在表示中の画面に応じてactive表示
- ログイン中ユーザーのメール表示
- モバイル/狭い画面では折り返し表示

重要:
- DB変更なし
- migrate不要
- composer不要

適用方法:
1. このZIPを tools\release_trusteps_cms_lab.bat にドラッグ＆ドロップ
2. mode=3、またはEnter
3. Run? に Y
4. Release completed successfully が出れば完了

確認手順:
1. どの画面でも上メニューが出るか確認
2. Dashboard / source_records / companies / 営業候補 に移動できるか確認
3. active表示がそれっぽく切り替わるか確認
