<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BizmapsScraperService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BizmapsImportController extends Controller
{
    public function index()
    {
        $prefectures = DB::table('prefectures')->orderBy('id')->get();
        $industries  = $this->getIndustries();
        return view('bizmaps.import', compact('prefectures', 'industries'));
    }

    public function getMunicipalities(Request $request)
    {
        $prefectureId = $request->input('prefecture_id');
        if (!$prefectureId) return response()->json([]);

        $municipalities = DB::table('municipalities')
            ->where('prefecture_id', $prefectureId)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return response()->json($municipalities);
    }

    public function getSubIndustries(Request $request)
    {
        $bigIndId = $request->input('big_ind_id');
        if (!$bigIndId) return response()->json([]);

        $industries = $this->getIndustries();
        foreach ($industries as $ind) {
            if ($ind['big_id'] == $bigIndId) return response()->json($ind['sub']);
        }
        return response()->json([]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'prefecture_id' => 'required|integer',
            'city_codes'    => 'nullable|array',
            'industry_type' => 'required|in:pref,city,big_ind,m_ind',
            'industry_id'   => 'nullable|integer',
            'limit'         => 'required|integer|min:1|max:500',
        ]);

        $prefectureId = $request->input('prefecture_id');
        $cityCodes    = $request->input('city_codes', []);
        $industryType = $request->input('industry_type');
        $industryId   = $request->input('industry_id');
        $limit        = (int) $request->input('limit', 50);

        $prefecture = DB::table('prefectures')->find($prefectureId);
        $urls       = $this->buildUrls($prefectureId, $cityCodes, $industryType, $industryId);

        Log::info('BIZMAPS preview', [
            'urls'          => $urls,
            'industry_type' => $industryType,
            'prefecture_id' => $prefectureId,
            'city_codes'    => $cityCodes,
        ]);

        $scraper = new BizmapsScraperService();
        $results = [];

        foreach ($urls as $url) {
            $fetched = $scraper->fetchList($url, $limit - count($results), false);
            $results = array_merge($results, $fetched);
            if (count($results) >= $limit) break;
        }

        $results = array_slice($results, 0, $limit);

        // 重複チェック
        $detailUrls   = array_filter(array_column($results, 'detail_url'));
        $existingUrls = DB::table('source_records')
            ->whereIn('source_url', $detailUrls)
            ->pluck('source_url')
            ->toArray();

        // 除外リストチェック
        $excludedUrls = DB::table('bizmaps_excluded_companies')
            ->whereIn('detail_url', $detailUrls)
            ->pluck('detail_url')
            ->toArray();

        $mainResults     = [];
        $excludedResults = [];

        foreach ($results as &$r) {
            $r['is_duplicate'] = in_array($r['detail_url'], $existingUrls);
            if (in_array($r['detail_url'], $excludedUrls)) {
                $excludedResults[] = $r;
            } else {
                $mainResults[] = $r;
            }
        }
        unset($r);

        // SSE用にdetail_urlリストをセッションに保存
        $detailUrlsForSse = array_map(fn($r) => $r['detail_url'], $results);
        session(['bizmaps_detail_urls' => $detailUrlsForSse]);

        // 検索条件をセッションに保存（再取得用）
        $searchCondition = [
            'prefecture_id' => $prefectureId,
            'prefecture_name' => $prefecture->name ?? '',
            'city_codes'    => $cityCodes,
            'industry_type' => $industryType,
            'industry_id'   => $industryId,
            'limit'         => $limit,
            'big_ind_name'  => $request->input('big_ind_name', ''),
            'm_ind_name'    => $request->input('m_ind_name', ''),
        ];
        session(['bizmaps_search_condition' => $searchCondition]);

        $industries = $this->getIndustries();
        $results = $mainResults; // 後方互換用
        return view('bizmaps.preview', compact('mainResults', 'excludedResults', 'results', 'prefecture', 'limit', 'searchCondition', 'industries'));
    }

    /**
     * SSEエンドポイント：1件ずつHP URLを取得してストリームで返す
     */
    public function fetchHpStream(Request $request): StreamedResponse
    {
        $detailUrls = session('bizmaps_detail_urls', []);

        return new StreamedResponse(function () use ($detailUrls) {
            $scraper = new BizmapsScraperService();

            foreach ($detailUrls as $index => $detailUrl) {
                if (!$detailUrl) {
                    $this->sseEmit([
                        'index'  => $index,
                        'hp_url' => null,
                        'status' => 'skip',
                    ]);
                    continue;
                }

                try {
                    $detail = $scraper->fetchDetailInfo($detailUrl);
                    $hpUrl  = $detail['hp_url']   ?? null;
                    $industry = $detail['industry'] ?? null;
                    $this->sseEmit([
                        'index'      => $index,
                        'hp_url'     => $hpUrl,
                        'industry'   => $industry,
                        'detail_url' => $detailUrl,
                        'status'     => $hpUrl ? 'found' : 'not_found',
                    ]);
                } catch (\Throwable $e) {
                    $this->sseEmit([
                        'index'  => $index,
                        'hp_url' => null,
                        'status' => 'error',
                    ]);
                }

                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            // 完了イベント
            echo "event: done\n";
            echo "data: " . json_encode(['total' => count($detailUrls)]) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    private function sseEmit(array $data): void
    {
        echo "data: " . json_encode($data) . "\n\n";
    }

    /**
     * 除外リストに追加
     */
    public function exclude(Request $request)
    {
        $item = $request->input('item', []);
        $detailUrl = $item['detail_url'] ?? null;
        if (!$detailUrl) return response()->json(['error' => 'no detail_url'], 400);

        DB::table('bizmaps_excluded_companies')->updateOrInsert(
            ['detail_url' => $detailUrl],
            [
                'name'        => $item['name']  ?? null,
                'pref'        => $item['pref']  ?? null,
                'city'        => $item['city']  ?? null,
                'excluded_at' => now(),
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * 除外リストから復活
     */
    public function unexclude(Request $request)
    {
        $detailUrl = $request->input('detail_url');
        if (!$detailUrl) return response()->json(['error' => 'no detail_url'], 400);

        DB::table('bizmaps_excluded_companies')->where('detail_url', $detailUrl)->delete();

        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $items   = $request->input('items', []);
        $saved   = 0;
        $skipped = 0;
        $now     = now();

        foreach ($items as $item) {
            $hpUrl     = $item['hp_url']     ?? null;
            $detailUrl = $item['detail_url'] ?? null;
            $sourceUrl = $hpUrl ?: $detailUrl;
            if (!$sourceUrl) { $skipped++; continue; }

            if (DB::table('source_records')->where('source_url', $sourceUrl)->exists()) {
                $skipped++;
                continue;
            }

            $normalizedDomain = null;
            if ($hpUrl) {
                $host             = parse_url($hpUrl, PHP_URL_HOST);
                $normalizedDomain = $host ? preg_replace('/^www\./', '', $host) : null;
            }

            DB::table('source_records')->insert([
                'source_type'       => 'bizmaps',
                'source_url'        => $sourceUrl,
                'normalized_domain' => $normalizedDomain,
                'name_norm'         => $item['name']     ?? null,
                'pref'              => $item['pref']     ?? null,
                'city'              => $item['city']     ?? null,
                'raw_json'          => json_encode([
                    'hp_url'     => $hpUrl,
                    'detail_url' => $detailUrl,
                    'industry'   => $item['industry'] ?? null,
                    'source'     => 'bizmaps',
                ], JSON_UNESCAPED_UNICODE),
                'fetched_at'  => $now,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $saved++;
        }

        return response()->json(['saved' => $saved, 'skipped' => $skipped]);
    }

    private function buildUrls(int $prefectureId, array $cityCodes, string $industryType, ?int $industryId): array
    {
        $urls = [];

        if ($industryType === 'pref') {
            $urls[] = "https://biz-maps.com/s/prefs/{$prefectureId}";
        } elseif ($industryType === 'city' && !empty($cityCodes)) {
            foreach ($cityCodes as $code) {
                $urls[] = "https://biz-maps.com/s/cities/{$code}";
            }
        } elseif ($industryType === 'big_ind' && $industryId) {
            if (!empty($cityCodes)) {
                foreach ($cityCodes as $code) {
                    $urls[] = "https://biz-maps.com/s/cities/{$code}?big_industry[]={$industryId}";
                }
            } else {
                $urls[] = "https://biz-maps.com/s/b-inds/{$industryId}";
            }
        } elseif ($industryType === 'm_ind' && $industryId) {
            if (!empty($cityCodes)) {
                foreach ($cityCodes as $code) {
                    $urls[] = "https://biz-maps.com/s/cities/{$code}?mid_industry[]={$industryId}";
                }
            } else {
                $urls[] = "https://biz-maps.com/s/m-inds/{$industryId}";
            }
        }

        return $urls;
    }

    private function getIndustries(): array
    {
        return [
            ['big_id' => 50,  'big_name' => '運輸・物流業界',            'sub' => [['id'=>162,'name'=>'倉庫'],['id'=>197,'name'=>'一般貨物輸送'],['id'=>237,'name'=>'海運'],['id'=>481,'name'=>'陸運'],['id'=>485,'name'=>'冷凍冷蔵車運行'],['id'=>508,'name'=>'空運'],['id'=>546,'name'=>'タクシー'],['id'=>573,'name'=>'鉄道'],['id'=>594,'name'=>'機械輸送'],['id'=>607,'name'=>'港湾運送'],['id'=>616,'name'=>'重量物輸送'],['id'=>651,'name'=>'バス'],['id'=>838,'name'=>'その他運輸・物流']]],
            ['big_id' => 53,  'big_name' => '建設・建築',                'sub' => [['id'=>179,'name'=>'その他電気設備工事'],['id'=>182,'name'=>'ゼネコン'],['id'=>190,'name'=>'建設資材'],['id'=>204,'name'=>'解体工事'],['id'=>207,'name'=>'その他建設・工事'],['id'=>208,'name'=>'建築設計'],['id'=>251,'name'=>'店舗デザイン'],['id'=>259,'name'=>'塗装工事・外装・エクステリア工事'],['id'=>266,'name'=>'電気設備工事'],['id'=>267,'name'=>'内装工事・床工事・フローリング工事'],['id'=>303,'name'=>'土木工事'],['id'=>350,'name'=>'リフォーム'],['id'=>360,'name'=>'空調設備工事'],['id'=>403,'name'=>'太陽光発電'],['id'=>447,'name'=>'配管工事・給排水設備工事'],['id'=>497,'name'=>'住宅建築・開発'],['id'=>513,'name'=>'注文型住宅建築'],['id'=>522,'name'=>'太陽光パネル'],['id'=>639,'name'=>'測量設計'],['id'=>649,'name'=>'土木設計']]],
            ['big_id' => 55,  'big_name' => '広告・制作業界',            'sub' => [['id'=>171,'name'=>'デザイン'],['id'=>211,'name'=>'広告代理店'],['id'=>220,'name'=>'印刷'],['id'=>222,'name'=>'映像・CM制作'],['id'=>226,'name'=>'市場調査・リサーチ'],['id'=>228,'name'=>'コンテンツ企画・制作'],['id'=>250,'name'=>'Webデザイン'],['id'=>270,'name'=>'広告制作'],['id'=>458,'name'=>'CG制作'],['id'=>474,'name'=>'インターネット広告代理店'],['id'=>845,'name'=>'その他広告']]],
            ['big_id' => 57,  'big_name' => 'エンタメ・娯楽',            'sub' => [['id'=>263,'name'=>'企業展示会・販促イベント'],['id'=>268,'name'=>'音楽'],['id'=>300,'name'=>'イベント'],['id'=>305,'name'=>'スポーツ'],['id'=>334,'name'=>'アミューズメント施設'],['id'=>363,'name'=>'パチンコ'],['id'=>394,'name'=>'ゴルフ場'],['id'=>677,'name'=>'カラオケ']]],
            ['big_id' => 59,  'big_name' => '不動産',                   'sub' => [['id'=>166,'name'=>'不動産売買・仲介'],['id'=>181,'name'=>'不動産賃貸・仲介'],['id'=>236,'name'=>'その他不動産'],['id'=>243,'name'=>'マンション・ビル管理'],['id'=>246,'name'=>'ビルメンテナンス'],['id'=>282,'name'=>'レンタルスペース'],['id'=>339,'name'=>'不動産管理'],['id'=>350,'name'=>'リフォーム'],['id'=>641,'name'=>'駐車場']]],
            ['big_id' => 61,  'big_name' => 'アウトソーシング・代行',    'sub' => [['id'=>234,'name'=>'翻訳・通訳'],['id'=>327,'name'=>'営業代行・販売代行'],['id'=>377,'name'=>'データ入力・事務作業代行'],['id'=>457,'name'=>'コールセンター・テレマーケティング'],['id'=>652,'name'=>'便利業'],['id'=>695,'name'=>'秘書代行・電話代行']]],
            ['big_id' => 62,  'big_name' => '士業',                     'sub' => [['id'=>408,'name'=>'税理士'],['id'=>547,'name'=>'会計士'],['id'=>566,'name'=>'社会保険労務士'],['id'=>597,'name'=>'弁護士'],['id'=>598,'name'=>'行政書士'],['id'=>846,'name'=>'司法書士']]],
            ['big_id' => 64,  'big_name' => '資源・素材業界',            'sub' => [['id'=>188,'name'=>'肥料・農薬'],['id'=>195,'name'=>'繊維'],['id'=>326,'name'=>'化学薬品'],['id'=>351,'name'=>'塗料'],['id'=>368,'name'=>'プラスチック製品'],['id'=>412,'name'=>'木材・パルプ'],['id'=>472,'name'=>'鋼材'],['id'=>489,'name'=>'金属製品']]],
            ['big_id' => 66,  'big_name' => 'その他サービス業界',        'sub' => [['id'=>150,'name'=>'その他サービス'],['id'=>310,'name'=>'検査'],['id'=>329,'name'=>'引越し'],['id'=>358,'name'=>'環境・廃棄物処理'],['id'=>388,'name'=>'葬儀'],['id'=>449,'name'=>'警備']]],
            ['big_id' => 68,  'big_name' => '飲食業界',                 'sub' => [['id'=>189,'name'=>'給食'],['id'=>232,'name'=>'和食'],['id'=>260,'name'=>'飲食宅配・弁当仕出し'],['id'=>275,'name'=>'居酒屋'],['id'=>291,'name'=>'カフェ・喫茶店'],['id'=>322,'name'=>'洋食・レストラン'],['id'=>376,'name'=>'お菓子・スイーツ'],['id'=>402,'name'=>'焼肉'],['id'=>512,'name'=>'その他外食'],['id'=>576,'name'=>'中華料理・ラーメン'],['id'=>579,'name'=>'寿司'],['id'=>627,'name'=>'ファーストフード'],['id'=>706,'name'=>'そば・うどん']]],
            ['big_id' => 70,  'big_name' => '医療・福祉業界',            'sub' => [['id'=>261,'name'=>'医療機器・器具メーカー'],['id'=>311,'name'=>'介護'],['id'=>364,'name'=>'訪問看護'],['id'=>410,'name'=>'施設介護サービス'],['id'=>426,'name'=>'調剤薬局'],['id'=>444,'name'=>'在宅介護サービス'],['id'=>488,'name'=>'医薬品メーカー'],['id'=>560,'name'=>'病院・療養所'],['id'=>596,'name'=>'児童福祉'],['id'=>648,'name'=>'障害者福祉']]],
            ['big_id' => 76,  'big_name' => '生活用品・嗜好品業界',      'sub' => [['id'=>160,'name'=>'食器・キッチン用品'],['id'=>196,'name'=>'玩具'],['id'=>219,'name'=>'スポーツ用品'],['id'=>283,'name'=>'ペット用品'],['id'=>308,'name'=>'日用品・生活用品'],['id'=>330,'name'=>'雑貨'],['id'=>430,'name'=>'家具・インテリア'],['id'=>462,'name'=>'生花・プリザーブドフラワー']]],
            ['big_id' => 79,  'big_name' => '製造業界',                 'sub' => [['id'=>167,'name'=>'化粧品製造'],['id'=>194,'name'=>'その他製造'],['id'=>199,'name'=>'紙類包装資材'],['id'=>287,'name'=>'機械部品'],['id'=>295,'name'=>'精密機器'],['id'=>318,'name'=>'金属加工'],['id'=>353,'name'=>'金属部品'],['id'=>437,'name'=>'防災・防犯機器'],['id'=>523,'name'=>'看板']]],
            ['big_id' => 82,  'big_name' => '機械業界',                 'sub' => [['id'=>215,'name'=>'センサー・計測機器'],['id'=>242,'name'=>'空調機器'],['id'=>315,'name'=>'建設機械'],['id'=>321,'name'=>'電力設備・発電設備'],['id'=>335,'name'=>'省エネ機器'],['id'=>386,'name'=>'電子機器'],['id'=>406,'name'=>'その他機械'],['id'=>414,'name'=>'工作機械'],['id'=>441,'name'=>'電気機器']]],
            ['big_id' => 84,  'big_name' => '小売・卸売業界',            'sub' => [['id'=>177,'name'=>'その他小売'],['id'=>184,'name'=>'化粧品販売'],['id'=>213,'name'=>'自動車ディーラー'],['id'=>223,'name'=>'スーパー'],['id'=>265,'name'=>'通信販売'],['id'=>299,'name'=>'食品販売'],['id'=>319,'name'=>'家具販売'],['id'=>325,'name'=>'雑貨販売'],['id'=>331,'name'=>'インターネット通販'],['id'=>378,'name'=>'中古車販売'],['id'=>382,'name'=>'ガソリンスタンド'],['id'=>442,'name'=>'お菓子屋'],['id'=>664,'name'=>'書店'],['id'=>673,'name'=>'コンビニ'],['id'=>674,'name'=>'ドラッグストア'],['id'=>680,'name'=>'花屋']]],
            ['big_id' => 87,  'big_name' => '自動車・輸送機器業界',      'sub' => [['id'=>361,'name'=>'自動車部品・カー用品'],['id'=>456,'name'=>'自動車'],['id'=>461,'name'=>'自動車整備'],['id'=>541,'name'=>'レンタカー・リース'],['id'=>577,'name'=>'二輪車']]],
            ['big_id' => 90,  'big_name' => 'レジャー・観光・宿泊',      'sub' => [['id'=>241,'name'=>'旅行'],['id'=>359,'name'=>'温泉施設'],['id'=>390,'name'=>'レジャー・テーマパーク'],['id'=>502,'name'=>'ホテル'],['id'=>747,'name'=>'観光バス'],['id'=>767,'name'=>'旅館']]],
            ['big_id' => 96,  'big_name' => 'IT・Web',                  'sub' => [['id'=>164,'name'=>'Webサービス・アプリ運営'],['id'=>169,'name'=>'サーバー・ネットワーク'],['id'=>173,'name'=>'システム開発'],['id'=>200,'name'=>'ソフトウェア販売'],['id'=>248,'name'=>'AI・人工知能'],['id'=>273,'name'=>'スマートフォンアプリ'],['id'=>277,'name'=>'Webコンサルティング'],['id'=>278,'name'=>'システム受託開発'],['id'=>298,'name'=>'eコマース'],['id'=>341,'name'=>'情報セキュリティ'],['id'=>413,'name'=>'クラウドサービス'],['id'=>836,'name'=>'その他IT・Web']]],
            ['big_id' => 98,  'big_name' => 'コンサルティング業界',      'sub' => [['id'=>153,'name'=>'不動産コンサルティング'],['id'=>205,'name'=>'ITコンサルティング'],['id'=>238,'name'=>'組織・人事コンサルティング'],['id'=>249,'name'=>'その他コンサルティング'],['id'=>286,'name'=>'経営コンサルティング'],['id'=>293,'name'=>'総合コンサルティング'],['id'=>398,'name'=>'建築コンサルティング'],['id'=>435,'name'=>'販売促進コンサルティング'],['id'=>542,'name'=>'Web広告運用コンサルティング']]],
            ['big_id' => 100, 'big_name' => '生活関連サービス業',        'sub' => [['id'=>156,'name'=>'ビル・商業施設清掃'],['id'=>297,'name'=>'ブライダル'],['id'=>343,'name'=>'フィットネスクラブ'],['id'=>465,'name'=>'エステティックサロン'],['id'=>531,'name'=>'接骨・柔道整復'],['id'=>533,'name'=>'クリーニング'],['id'=>548,'name'=>'美容院'],['id'=>612,'name'=>'水道'],['id'=>684,'name'=>'保育・託児'],['id'=>702,'name'=>'整体'],['id'=>758,'name'=>'パソコン・スマホ修理']]],
            ['big_id' => 104, 'big_name' => 'アパレル・美容業界',        'sub' => [['id'=>175,'name'=>'靴'],['id'=>206,'name'=>'レディースアパレルメーカー'],['id'=>217,'name'=>'アパレル'],['id'=>258,'name'=>'美容サロン'],['id'=>264,'name'=>'メンズアパレルメーカー'],['id'=>276,'name'=>'ジュエリー・アクセサリー'],['id'=>357,'name'=>'化粧品'],['id'=>460,'name'=>'和服・呉服'],['id'=>509,'name'=>'エステサロン']]],
            ['big_id' => 107, 'big_name' => '機械関連サービス業界',      'sub' => [['id'=>274,'name'=>'その他機械関連サービス'],['id'=>373,'name'=>'機械設計'],['id'=>519,'name'=>'機械レンタル・リース'],['id'=>599,'name'=>'機械修理'],['id'=>630,'name'=>'受託製造']]],
            ['big_id' => 111, 'big_name' => '商社業界',                 'sub' => [['id'=>159,'name'=>'その他専門商社'],['id'=>174,'name'=>'鉄鋼・金属専門商社'],['id'=>176,'name'=>'総合商社'],['id'=>247,'name'=>'工業用機械専門商社'],['id'=>280,'name'=>'機械専門商社'],['id'=>338,'name'=>'電子部品専門商社'],['id'=>352,'name'=>'医療関連専門商社'],['id'=>374,'name'=>'化学専門商社'],['id'=>575,'name'=>'食品専門商社']]],
            ['big_id' => 120, 'big_name' => 'マスコミ・出版業界',        'sub' => [['id'=>192,'name'=>'電子書籍出版'],['id'=>198,'name'=>'出版社'],['id'=>224,'name'=>'テレビ番組制作'],['id'=>231,'name'=>'芸能プロダクション'],['id'=>340,'name'=>'新聞'],['id'=>477,'name'=>'ラジオ番組制作'],['id'=>839,'name'=>'その他マスコミ・出版']]],
            ['big_id' => 134, 'big_name' => '食品業界',                 'sub' => [['id'=>152,'name'=>'その他食品'],['id'=>203,'name'=>'食肉'],['id'=>256,'name'=>'健康食品'],['id'=>313,'name'=>'和菓子'],['id'=>344,'name'=>'農'],['id'=>395,'name'=>'パン'],['id'=>422,'name'=>'米飯・惣菜'],['id'=>434,'name'=>'乳製品'],['id'=>440,'name'=>'洋菓子'],['id'=>466,'name'=>'水産'],['id'=>469,'name'=>'調味料'],['id'=>498,'name'=>'酒・ワイン'],['id'=>666,'name'=>'飲料'],['id'=>675,'name'=>'菓子']]],
            ['big_id' => 143, 'big_name' => 'ゲーム業界',               'sub' => [['id'=>202,'name'=>'ゲームソフト開発'],['id'=>540,'name'=>'ソーシャルゲーム開発'],['id'=>842,'name'=>'その他ゲーム']]],
            ['big_id' => 144, 'big_name' => '通信',                     'sub' => [['id'=>229,'name'=>'インターネットサービスプロバイダ'],['id'=>381,'name'=>'携帯電話'],['id'=>436,'name'=>'その他通信'],['id'=>494,'name'=>'MVNO・格安SIM事業者'],['id'=>572,'name'=>'電話機・ビジネスフォン'],['id'=>681,'name'=>'コピー機・OA機器']]],
            ['big_id' => 145, 'big_name' => '人材業界',                 'sub' => [['id'=>254,'name'=>'人材派遣'],['id'=>342,'name'=>'企業研修'],['id'=>346,'name'=>'人材紹介'],['id'=>668,'name'=>'求人広告代理店'],['id'=>693,'name'=>'職業紹介所'],['id'=>757,'name'=>'ヘッドハンティング'],['id'=>835,'name'=>'その他人材']]],
            ['big_id' => 146, 'big_name' => '教育・学習業界',            'sub' => [['id'=>307,'name'=>'通信教育'],['id'=>324,'name'=>'各種スクール・教室（ビジネス）'],['id'=>347,'name'=>'児童保育'],['id'=>432,'name'=>'各種スクール・教室（趣味）'],['id'=>443,'name'=>'塾・受験予備校'],['id'=>499,'name'=>'語学学習スクール'],['id'=>545,'name'=>'専門学校・専修学校'],['id'=>636,'name'=>'自動車教習所'],['id'=>671,'name'=>'学校'],['id'=>844,'name'=>'その他教育業界']]],
            ['big_id' => 147, 'big_name' => '家電業界',                 'sub' => [['id'=>314,'name'=>'総合家電メーカー'],['id'=>580,'name'=>'カメラ'],['id'=>585,'name'=>'テレビ'],['id'=>587,'name'=>'エアコン'],['id'=>590,'name'=>'パソコン'],['id'=>602,'name'=>'照明器具'],['id'=>764,'name'=>'その他家電']]],
            ['big_id' => 148, 'big_name' => '金融業界',                 'sub' => [['id'=>333,'name'=>'貸金'],['id'=>362,'name'=>'クレジット・信販・決済代行'],['id'=>365,'name'=>'生命保険'],['id'=>367,'name'=>'投資'],['id'=>389,'name'=>'保険代理店'],['id'=>484,'name'=>'損害保険'],['id'=>521,'name'=>'保険'],['id'=>552,'name'=>'証券'],['id'=>623,'name'=>'銀行'],['id'=>624,'name'=>'信用金庫・信用組合'],['id'=>833,'name'=>'地方銀行']]],
            ['big_id' => 149, 'big_name' => 'エネルギー業界',            'sub' => [['id'=>356,'name'=>'再生可能エネルギー'],['id'=>524,'name'=>'電力発電'],['id'=>567,'name'=>'ソーラーシステム・太陽光発電'],['id'=>657,'name'=>'新電力'],['id'=>841,'name'=>'その他エネルギー']]],
            ['big_id' => 157, 'big_name' => '公共機関・団体・特殊法人',  'sub' => [['id'=>556,'name'=>'社団法人'],['id'=>557,'name'=>'協会'],['id'=>558,'name'=>'財団法人'],['id'=>609,'name'=>'協同組合'],['id'=>619,'name'=>'NPO'],['id'=>655,'name'=>'官公庁・自治体・各種団体']]],
            ['big_id' => 834, 'big_name' => 'その他業界',               'sub' => [['id'=>843,'name'=>'その他']]],
        ];
    }
}
