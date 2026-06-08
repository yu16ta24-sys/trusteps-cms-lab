TRUSTEPS CMS Lab - Sales Candidates v0.11.0

追加・更新ファイル:
- UPDATE_MANIFEST.json
- .trusteps-cms-lab-update
- VERSION
- app/Http/Controllers/CompanyController.php
- routes/web.php
- resources/views/companies/candidates.blade.php

削除ファイル:
- なし

目的:
Phase0-11: 4軸スコアをもとに、営業候補一覧を表示できるようにします。

追加URL:
- /companies/candidates

変更内容:
- 未kill・未mergedのcompanyだけを営業候補ベースとして表示
- 機会スコア / リスクスコア / 簡易判定 / 優先度を表示
- プリセット切替を追加
  - 推奨：高機会・低リスク
  - 高機会
  - 未採点あり
  - 全active
- 業種・キーワード検索
- 優先度順で表示

優先度について:
機会スコアを強めに評価し、リスクスコアを減点する並び替え用の簡易指標です。
絶対値ではなく、候補リストの表示順を作るためのスコアです。

重要:
- DB変更なし
- migrate不要
- composer不要
- これは営業送信管理ではなく、営業候補抽出画面です

適用方法:
1. このZIPを tools\release_trusteps_cms_lab.bat にドラッグ＆ドロップ
2. mode=3、またはEnter
3. Run? に Y
4. Release completed successfully が出れば完了

確認手順:
1. /companies/candidates を開く
2. 推奨候補が表示されるか確認
3. プリセットを「全active」に変える
4. 会社が表示されるか確認
5. 詳細ボタンでcompany詳細へ行けるか確認
