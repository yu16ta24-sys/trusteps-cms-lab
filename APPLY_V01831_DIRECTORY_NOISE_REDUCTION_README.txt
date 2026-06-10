TRUSTEPS CMS Lab v0.18.3.1

目的:
- 名簿URL抽出の候補ノイズ削減。
- 名簿元ドメインと同じURLを最終候補に出さない。
- 事業者詳細ページ候補は、候補表に出すのではなく、詳細掘り下げ用の中間ページとして裏側で利用する。
- 同一候補ドメインは1件に集約する。
- 既にsource_recordsへ登録済みの公式/ビルダー系ドメインは候補表から非表示にする。

入れたもの:
- DirectoryLinkExtractorの最終候補フィルタ強化。
- 詳細候補(directory_detail_candidate)を候補表から除外し、1階層掘りの対象としてだけ扱う。
- 詳細ページ由来の外部リンクを優先。
- 同一URL/同一候補ドメインの非表示カウント。
- DiscoveryLabController側で名簿元ドメイン・既存DBドメイン・プレビュー内重複ドメインを候補非表示。
- プレビュー画面に候補ノイズ削減カウントを表示。

入れていないもの:
- migrationなし。
- seederなし。
- Googleマップスクレイピングなし。
- Places APIなし。
- Web検索APIなし。
- HP解析なし。
- company自動作成なし。

適用後コマンド:
cd /var/www/trusteps-cms-lab
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

注意:
- v0.18.3からの差分パッチ。
- DB追加なしのため migrate は不要。
