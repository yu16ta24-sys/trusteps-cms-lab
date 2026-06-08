TRUSTEPS CMS Lab - Company Scores Diff v0.9.0

追加・更新ファイル:
- UPDATE_MANIFEST.json
- .trusteps-cms-lab-update
- VERSION
- app/Models/CompanyScore.php
- app/Models/Company.php
- app/Http/Controllers/CompanyController.php
- routes/web.php
- resources/views/companies/show.blade.php

削除ファイル:
- なし

目的:
Phase0-9: 4軸スコアの手動入力入口を追加します。

今回できること:
- company詳細から4軸スコアを手動登録・更新
- hp_weakness
- self_update_fit
- dev_difficulty
- portal_dependence
- 各軸に value 0〜5 / confidence 0.3,0.6,0.9 / 判断メモを保存
- company_scores に algo_version=v1 で保存
- scored_by / scored_at を記録
- reason_json に手動メモを保存

まだやらないこと:
- 自動スコアリング
- auto_suggested_value
- スコア一覧・集計ビュー
- 散布図
- BI用view
- score履歴の複数世代管理

適用方法:
1. このZIPを tools\release_trusteps_cms_lab.bat にドラッグ＆ドロップ
2. mode=3、またはEnter
3. Run? に Y
4. Release completed successfully が出れば完了

確認手順:
1. https://cms-lab.trusteps.co.jp にログイン
2. companies詳細を開く
3. 4軸スコア欄で各スコアを入力
4. 保存
5. 現在値・scored_by・scored_atが表示されるか確認

補足:
機会スコアとリスクスコアは合算しません。
この画面は「観測後の人間評価層」を入力するための入口です。
