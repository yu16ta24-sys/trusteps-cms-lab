TRUSTEPS CMS Lab v0.18.1
業界スコアの箱（手動編集版）

この更新の目的
----
業界ごとのCMS事業適性・参入余白を、仮説/実測メモとして後から編集できる箱を追加します。
個社スコアや営業候補ランキングにはまだ反映しません。

追加される主な機能
----
- /industries/scores 業界スコア一覧
- /industries/scores/{industry}/edit 業界別スコア編集
- industry_score_axes テーブル
- industry_scores テーブル
- 初期スコア軸10個
- 0〜5点の手動編集
- confidence: 低 / 中 / 高
- score_type: hypothesis / observed / mixed
- 軸ごとのメモ欄

今回あえて入れていないもの
----
- 自動算出
- 総合点
- 個社スコアとの合算
- 営業候補ランキングへの反映
- HP解析との連動
- Googleマップ/Places/Web検索API

適用後の操作
----
Release Launcherがmigration/seederを自動実行しない場合は、サーバー上で以下を実行してください。

php artisan migrate
php artisan db:seed --class=IndustryScoreAxisSeeder
php artisan optimize:clear

確認URL
----
/industries/scores

安全設計メモ
----
業界スコアは研究用の編集箱です。
company_scoresとは完全に分離し、現時点では営業候補一覧やDashboardの判定には使いません。
