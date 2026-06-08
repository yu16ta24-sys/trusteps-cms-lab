TRUSTEPS CMS Lab - Municipality Filter UI v0.9.2

追加・更新ファイル:
- UPDATE_MANIFEST.json
- .trusteps-cms-lab-update
- VERSION
- resources/views/companies/create_from_source.blade.php

削除ファイル:
- なし

目的:
市区町村マスタが増えたときに、地域プルダウンが長くなりすぎる問題を軽減します。

変更内容:
- company作成画面に「都道府県で絞り込み」を追加
- 選択した都道府県に応じて「地域（市区町村マスタ）」の候補を画面内JSで絞り込み
- API追加なし
- DB変更なし
- municipality_idの保存仕様はそのまま

理由:
全国市区町村を入れると、1つのプルダウンだけでは探すのが面倒になるため。
まずは軽量に、都道府県フィルターで対応します。

適用方法:
1. このZIPを tools\release_trusteps_cms_lab.bat にドラッグ＆ドロップ
2. mode=3、またはEnter
3. Run? に Y
4. Release completed successfully が出れば完了

確認手順:
1. source_record詳細から「このデータからcompany作成」を開く
2. 「都道府県で絞り込み」が表示されるか確認
3. 都道府県を選ぶと、市区町村候補が絞られるか確認
4. company作成できるか確認
