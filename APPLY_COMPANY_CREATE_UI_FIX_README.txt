TRUSTEPS CMS Lab - Company Create UI Fix v0.9.1

追加・更新ファイル:
- UPDATE_MANIFEST.json
- .trusteps-cms-lab-update
- VERSION
- app/Http/Controllers/CompanyController.php
- resources/views/companies/create_from_source.blade.php

削除ファイル:
- なし

目的:
company作成画面で「市区町村マスタ」と「都道府県/市区町村名の手入力」が並んでいて分かりにくく、
矛盾データを作れる状態だったため、UIを整理します。

変更内容:
- company作成画面から「都道府県」「市区町村名」の手入力欄を非表示
- 表示項目を「地域（市区町村マスタ）」に整理
- pref/city はhiddenの空値として送信
- 保存時、municipality_idが選択されている場合は pref/city をnull保存
- DBカラム自体は残す

理由:
- municipality_id が正規の地域マスタ
- pref/city は将来、市区町村マスタ外データを扱うための退避・補助用
- 現時点で同時入力させると、長野市と熊本県のような矛盾が作れてしまうため

適用方法:
1. このZIPを tools\release_trusteps_cms_lab.bat にドラッグ＆ドロップ
2. mode=3、またはEnter
3. Run? に Y
4. Release completed successfully が出れば完了

確認手順:
1. source_record詳細から「このデータからcompany作成」を開く
2. 「都道府県」「市区町村名」の手入力欄が消えているか確認
3. 「地域（市区町村マスタ）」だけ選ぶ
4. company作成できるか確認
