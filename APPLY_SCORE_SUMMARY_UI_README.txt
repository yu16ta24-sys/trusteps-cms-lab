TRUSTEPS CMS Lab - Score Summary UI v0.9.3

追加・更新ファイル:
- UPDATE_MANIFEST.json
- .trusteps-cms-lab-update
- VERSION
- resources/views/companies/show.blade.php

削除ファイル:
- なし

目的:
4軸スコア保存後の点数が分かりにくかったため、company詳細画面にサマリー表示を追加します。

変更内容:
- 4軸スコア欄の上部にサマリーを追加
- 機会スコア = hp_weakness + self_update_fit / 10
- リスクスコア = dev_difficulty + portal_dependence / 10
- 簡易判定を表示
  - 高機会・低リスク
  - 高機会・高リスク
  - 低機会・高リスク
  - 低機会・低リスク
  - 要確認
  - 未採点あり
- 各スコアカード下部の現在点を目立つ表示へ変更

重要:
機会スコアとリスクスコアは合算しません。
単純な総合点にすると「高機会・高リスク」と「普通に微妙」が同じ扱いになるためです。

適用方法:
1. このZIPを tools\release_trusteps_cms_lab.bat にドラッグ＆ドロップ
2. mode=3、またはEnter
3. Run? に Y
4. Release completed successfully が出れば完了

確認手順:
1. company詳細を開く
2. 4軸スコア欄の上に「機会」「リスク」「簡易判定」が出るか確認
3. 4軸スコアを変更して保存
4. サマリーと各カードの現在点が更新されるか確認
