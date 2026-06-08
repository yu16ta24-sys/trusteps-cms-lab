TRUSTEPS CMS Lab - Company Index Enhance v0.10.0

追加・更新ファイル:
- UPDATE_MANIFEST.json
- .trusteps-cms-lab-update
- VERSION
- app/Http/Controllers/CompanyController.php
- resources/views/companies/index.blade.php

削除ファイル:
- なし

目的:
Phase0-10: company一覧を、営業候補抽出の手前で使える一覧画面へ強化します。

変更内容:
- company一覧に4軸スコア由来の機会スコア / リスクスコアを表示
- 簡易判定を表示
  - 高機会・低リスク
  - 高機会・高リスク
  - 低機会・高リスク
  - 低機会・低リスク
  - 要確認
  - 未採点あり
- kill状態を表示
- source数 / domain数 / kill_flag数を表示
- status / kill状態フィルターを追加
- 画面全体の見た目をcompany詳細画面のトーンに合わせて改善
- 上部にTotal / Active / Killed / Scoredのサマリーカードを追加

重要:
- DB変更なし
- migrate不要
- composer不要
- 機会スコアとリスクスコアは合算しません

適用方法:
1. このZIPを tools\release_trusteps_cms_lab.bat にドラッグ＆ドロップ
2. mode=3、またはEnter
3. Run? に Y
4. Release completed successfully が出れば完了

確認手順:
1. companies一覧を開く
2. 機会スコア / リスクスコア / 簡易判定が表示されるか確認
3. kill状態フィルターが効くか確認
4. 詳細ボタンでcompany詳細へ遷移できるか確認
