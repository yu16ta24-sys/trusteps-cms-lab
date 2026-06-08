TRUSTEPS CMS Lab - Company Show Style v0.9.4

追加・更新ファイル:
- UPDATE_MANIFEST.json
- .trusteps-cms-lab-update
- VERSION
- resources/views/layouts/app.blade.php
- resources/views/companies/show.blade.php

削除ファイル:
- なし

目的:
company詳細画面、とくに4軸スコア周りを含む全体UIを見やすく・おしゃれに改善する。

変更内容:
- 共通レイアウトの見た目を底上げ
- ボタン、フォーム、バッジ、テーブル、カードの共通スタイル強化
- company詳細画面をヒーロー + セクションカード構成に再設計
- 4軸スコアのサマリー表示をより目立つデザインへ改善
- 各スコアカードの現在点表示を大きく見やすく調整
- 会社基本情報 / domains / source links / kill_flags を視認しやすいレイアウトに整理

重要:
- 機会スコアとリスクスコアは引き続き合算しない
- DB変更なし
- migrate不要

適用方法:
1. このZIPを tools\\release_trusteps_cms_lab.bat にドラッグ＆ドロップ
2. mode=3（Enterでも可）
3. Run? に Y
4. Release completed successfully が出れば完了

確認手順:
1. company詳細画面を開く
2. 画面上部の見た目が大きく変わっているか確認
3. 4軸スコアのサマリー（機会 / リスク / 簡易判定）が見やすく表示されるか確認
4. 各カードの現在点表示が目立っているか確認
5. kill_flag追加・解除、4軸スコア保存、統合/Undo導線がそのまま動くか確認
