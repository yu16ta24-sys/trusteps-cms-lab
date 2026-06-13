<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BizmapsScraperService;
use App\Services\BizmapsIndustryMapper;
use App\Support\NameNormalizer;
use App\Support\UrlNormalizer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BizmapsImportController extends Controller
{
    public function index()
    {
        $prefectures     = DB::table('prefectures')->orderBy('id')->get();
        $industries      = $this->getIndustries();
        $searchCondition = session('bizmaps_search_condition', []);
        return view('bizmaps.import', compact('prefectures', 'industries', 'searchCondition'));
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
        set_time_limit(300);

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

        // 1. 初回フェッチ
        $results = [];
        foreach ($urls as $url) {
            $fetched = $scraper->fetchList($url, $limit - count($results), false);
            $results = array_merge($results, $fetched);
            if (count($results) >= $limit) break;
        }
        $results = array_slice($results, 0, $limit);

        // 1b. HP URL 同期取得（fetch_hp=1 のとき）
        if ($request->boolean('fetch_hp')) {
            set_time_limit(300);
            foreach ($results as &$r) {
                if (!empty($r['detail_url'])) {
                    try {
                        $detail = $scraper->fetchDetailInfo($r['detail_url']);
                        if (!empty($detail['hp_url']))   $r['hp_url']   = $detail['hp_url'];
                        if (!empty($detail['industry'])) $r['industry'] = $detail['industry'];
                    } catch (\Throwable $e) {
                        // skip on error
                    }
                }
            }
            unset($r);
        }

        // 2. 重複チェック（active と is_excluded を分離）
        $detailUrls = array_values(array_filter(array_column($results, 'detail_url')));
        $allUrls    = array_values(array_unique(array_filter(array_merge(
            $detailUrls,
            array_column($results, 'hp_url')
        ))));

        $existingActiveUrls = DB::table('source_records')
            ->whereIn('source_url', $allUrls)
            ->where(fn($q) => $q->whereNull('is_excluded')->orWhere('is_excluded', false))
            ->pluck('source_url')
            ->toArray();

        $existingExcludedUrls = DB::table('source_records')
            ->whereIn('source_url', $allUrls)
            ->where('is_excluded', true)
            ->pluck('source_url')
            ->toArray();

        $excludedUrls = DB::table('bizmaps_excluded_companies')
            ->whereIn('detail_url', $detailUrls)
            ->pluck('detail_url')
            ->toArray();

        $normalizedAllUrls  = array_values(array_unique(array_filter(array_map([$this, 'normalizeUrl'], $allUrls))));
        $rawDomainUrls      = $normalizedAllUrls
            ? DB::table('domains')
                ->whereIn(DB::raw("LOWER(TRIM(TRAILING '/' FROM url))"), $normalizedAllUrls)
                ->pluck('url')->toArray()
            : [];
        $existingDomainUrls = array_flip(array_map([$this, 'normalizeUrl'], $rawDomainUrls));

        // 名前+市区町村の複合照合用：同一都道府県のcompanyを取得
        $existingByName = [];
        $prefectureName = $prefecture->name ?? '';
        if ($prefectureName) {
            DB::table('companies')
                ->leftJoin('municipalities', 'companies.municipality_id', '=', 'municipalities.id')
                ->select('companies.display_name', 'companies.city as comp_city', 'municipalities.name as muni_name')
                ->where('companies.is_killed', false)
                ->where(function ($q) use ($prefectureId, $prefectureName) {
                    $q->where('municipalities.prefecture_id', $prefectureId)
                      ->orWhere('companies.pref', $prefectureName);
                })
                ->get()
                ->each(function ($c) use (&$existingByName) {
                    $normName = $this->normalizeName($c->display_name ?? '');
                    if ($normName) {
                        $existingByName[$normName][] = $c->muni_name ?? $c->comp_city ?? '';
                    }
                });
        }

        $mainResults           = [];
        $excludedResults       = [];
        $excludedSourceResults = [];
        $companyExistedResults = [];

        foreach ($results as &$r) {
            $hpUrl = $r['hp_url'] ?? null;
            $r['is_duplicate'] = in_array($r['detail_url'], $existingActiveUrls)
                || ($hpUrl && in_array($hpUrl, $existingActiveUrls));
            $r['is_excluded_source'] = in_array($r['detail_url'], $existingExcludedUrls)
                || ($hpUrl && in_array($hpUrl, $existingExcludedUrls));
            $r['is_company_existed'] = isset($existingDomainUrls[$this->normalizeUrl($r['detail_url'] ?? null)])
                || ($hpUrl && isset($existingDomainUrls[$this->normalizeUrl($hpUrl)]))
                || $this->matchesExistingCompany($r, $existingByName);
            if (in_array($r['detail_url'], $excludedUrls)) {
                $excludedResults[] = $r;
            } elseif ($r['is_excluded_source']) {
                $excludedSourceResults[] = $r;
            } elseif ($r['is_company_existed']) {
                $companyExistedResults[] = $r;
            } else {
                $mainResults[] = $r;
            }
        }
        unset($r);

        // 3. is_excluded / bizmaps_excluded で消費されたスロットを補充フェッチ
        //    BIZMAPS に次ページがある場合のみ有効。同一 URL セットで件数を増やして
        //    再取得し、初回フェッチ済み分をスキップして差分だけ処理する。
        $shortage = count($excludedSourceResults) + count($excludedResults) + count($companyExistedResults);
        if ($shortage > 0) {
            $supplementTarget = $limit + $shortage + 5; // +5 は安全マージン
            $allFetched = [];
            foreach ($urls as $url) {
                $fetched = $scraper->fetchList($url, $supplementTarget - count($allFetched), false);
                $allFetched = array_merge($allFetched, $fetched);
                if (count($allFetched) >= $supplementTarget) break;
            }

            // 初回取得分（先頭 count($results) 件）を除いた差分を取り出す
            $seenUrls  = array_flip(array_filter(array_column($results, 'detail_url')));
            $suppItems = array_values(array_filter(
                array_slice($allFetched, count($results)),
                fn($r) => !isset($seenUrls[$r['detail_url'] ?? ''])
            ));

            if (!empty($suppItems)) {
                $suppDetailUrls = array_values(array_filter(array_column($suppItems, 'detail_url')));
                $suppAllUrls    = array_values(array_unique(array_filter(array_merge(
                    $suppDetailUrls,
                    array_column($suppItems, 'hp_url')
                ))));

                $suppActiveUrls = DB::table('source_records')
                    ->whereIn('source_url', $suppAllUrls)
                    ->where(fn($q) => $q->whereNull('is_excluded')->orWhere('is_excluded', false))
                    ->pluck('source_url')
                    ->toArray();

                $suppExcludedUrls = DB::table('source_records')
                    ->whereIn('source_url', $suppAllUrls)
                    ->where('is_excluded', true)
                    ->pluck('source_url')
                    ->toArray();

                $suppBzmUrls = DB::table('bizmaps_excluded_companies')
                    ->whereIn('detail_url', $suppDetailUrls)
                    ->pluck('detail_url')
                    ->toArray();

                $normalizedSuppUrls = array_values(array_unique(array_filter(array_map([$this, 'normalizeUrl'], $suppAllUrls))));
                $rawSuppDomains     = $normalizedSuppUrls
                    ? DB::table('domains')
                        ->whereIn(DB::raw("LOWER(TRIM(TRAILING '/' FROM url))"), $normalizedSuppUrls)
                        ->pluck('url')->toArray()
                    : [];
                $suppDomainUrls     = array_flip(array_map([$this, 'normalizeUrl'], $rawSuppDomains));

                foreach ($suppItems as &$r) {
                    $hpUrl = $r['hp_url'] ?? null;
                    $r['is_duplicate'] = in_array($r['detail_url'], $suppActiveUrls)
                        || ($hpUrl && in_array($hpUrl, $suppActiveUrls));
                    $r['is_excluded_source'] = in_array($r['detail_url'], $suppExcludedUrls)
                        || ($hpUrl && in_array($hpUrl, $suppExcludedUrls));
                    $r['is_company_existed'] = isset($suppDomainUrls[$this->normalizeUrl($r['detail_url'] ?? null)])
                        || ($hpUrl && isset($suppDomainUrls[$this->normalizeUrl($hpUrl)]))
                        || $this->matchesExistingCompany($r, $existingByName);
                    if (in_array($r['detail_url'], $suppBzmUrls)) {
                        $excludedResults[] = $r;
                    } elseif ($r['is_excluded_source']) {
                        $excludedSourceResults[] = $r;
                    } elseif ($r['is_company_existed']) {
                        $companyExistedResults[] = $r;
                    } else {
                        $mainResults[] = $r;
                    }
                }
                unset($r);

                // SSE 用に補充分の detail_url もセッションに含める
                $results = array_merge($results, $suppItems);
            }

            // ユーザーが指定した件数上限にキャップ
            $mainResults = array_slice($mainResults, 0, $limit);
        }

        // SSE用に mainResults の detail_url + name をセッションに保存
        $sseItems = array_values(array_map(fn($r) => [
            'detail_url' => $r['detail_url'],
            'name'       => $r['name'] ?? null,
        ], $mainResults));
        session(['bizmaps_detail_urls' => $sseItems]);

        // 検索条件をセッションに保存（再取得用）
        $searchCondition = [
            'prefecture_id'   => $prefectureId,
            'prefecture_name' => $prefecture->name ?? '',
            'city_codes'      => $cityCodes,
            'industry_type'   => $industryType,
            'industry_id'     => $industryId,
            'limit'           => $limit,
            'big_ind_name'    => $request->input('big_ind_name', ''),
            'm_ind_name'      => $request->input('m_ind_name', ''),
        ];
        session(['bizmaps_search_condition' => $searchCondition]);

        $industries = $this->getIndustries();
        $results    = $mainResults; // 後方互換用
        return view('bizmaps.preview', compact('mainResults', 'excludedResults', 'excludedSourceResults', 'companyExistedResults', 'results', 'prefecture', 'limit', 'searchCondition', 'industries'));
    }

    /**
     * SSEエンドポイント：BIZMAPSリスト取得の進捗をストリームで返す
     * 完了後に結果をセッションに保存する
     */
    public function previewStream(Request $request): StreamedResponse
    {
        $prefectureId = (int)$request->input('prefecture_id');
        $cityCodes    = (array)$request->input('city_codes', []);
        $industryType = $request->input('industry_type', 'pref');
        $industryId   = $request->input('industry_id') ? (int)$request->input('industry_id') : null;
        $limit        = max(1, min(500, (int)$request->input('limit', 50)));

        $prefecture = DB::table('prefectures')->find($prefectureId);
        $urls       = $this->buildUrls($prefectureId, $cityCodes, $industryType, $industryId);

        $searchCondition = [
            'prefecture_id'   => $prefectureId,
            'prefecture_name' => $prefecture->name ?? '',
            'city_codes'      => $cityCodes,
            'industry_type'   => $industryType,
            'industry_id'     => $industryId,
            'limit'           => $limit,
            'big_ind_name'    => $request->input('big_ind_name', ''),
            'm_ind_name'      => $request->input('m_ind_name', ''),
        ];
        session(['bizmaps_search_condition' => $searchCondition]);
        session()->save();

        $prefectureId2  = $prefectureId;
        $prefectureName = $prefecture->name ?? '';

        return new StreamedResponse(function () use (
            $urls, $limit, $prefectureId2, $prefectureName
        ) {
            set_time_limit(0);
            if (ob_get_level() === 0) ob_start();

            $scraper = new BizmapsScraperService();

            // 重複チェック用データをプリロード
            $excludedDetailUrlSet = array_flip(
                DB::table('bizmaps_excluded_companies')->pluck('detail_url')->toArray()
            );

            $existingByName = [];
            if ($prefectureName) {
                DB::table('companies')
                    ->leftJoin('municipalities', 'companies.municipality_id', '=', 'municipalities.id')
                    ->select('companies.display_name', 'companies.city as comp_city', 'municipalities.name as muni_name')
                    ->where('companies.is_killed', false)
                    ->where(function ($q) use ($prefectureId2, $prefectureName) {
                        $q->where('municipalities.prefecture_id', $prefectureId2)
                          ->orWhere('companies.pref', $prefectureName);
                    })
                    ->get()
                    ->each(function ($c) use (&$existingByName) {
                        $normName = $this->normalizeName($c->display_name ?? '');
                        if ($normName) {
                            $existingByName[$normName][] = $c->muni_name ?? $c->comp_city ?? '';
                        }
                    });
            }

            // ページ単位で重複チェック: source_records をバッチ照会
            $filterPage = function (array $items) use ($excludedDetailUrlSet, $existingByName): array {
                $detailUrls = array_values(array_filter(array_column($items, 'detail_url')));
                $registeredUrlSet = [];
                if (!empty($detailUrls)) {
                    $registeredUrlSet = array_flip(
                        DB::table('source_records')
                            ->whereIn('source_url', $detailUrls)
                            ->pluck('source_url')
                            ->toArray()
                    );
                }
                return array_values(array_filter($items, function ($item) use (
                    $excludedDetailUrlSet, $registeredUrlSet, $existingByName
                ) {
                    $detailUrl = $item['detail_url'] ?? null;
                    if (isset($excludedDetailUrlSet[$detailUrl])) return false;
                    if (isset($registeredUrlSet[$detailUrl])) return false;
                    return !$this->matchesExistingCompany($item, $existingByName);
                }));
            };

            $mainResults  = [];
            $scannedTotal = 0;

            foreach ($urls as $url) {
                if (count($mainResults) >= $limit) break;
                $scannedOffset = $scannedTotal;
                $newOffset     = count($mainResults);
                $remaining     = $limit - $newOffset;
                $urlScanned    = 0;

                $fetched = $scraper->fetchListWithProgress(
                    $url,
                    $remaining,
                    function (int $scanned, int $newCount, int $total, int $page) use ($scannedOffset, $newOffset, $limit) {
                        $this->sseEmit([
                            'scanned'   => $scannedOffset + $scanned,
                            'new_count' => $newOffset + $newCount,
                            'total'     => $limit,
                            'page'      => $page,
                        ]);
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    },
                    $filterPage,
                    $urlScanned
                );

                $scannedTotal += $urlScanned;
                foreach ($fetched as &$item) {
                    $item['is_duplicate'] = false;
                }
                unset($item);
                $mainResults = array_merge($mainResults, $fetched);
            }

            $mainResults = array_slice($mainResults, 0, $limit);

            $sseItems = array_values(array_map(fn($r) => [
                'detail_url' => $r['detail_url'],
                'name'       => $r['name'] ?? null,
            ], $mainResults));

            session()->start();
            session([
                'bizmaps_detail_urls'     => $sseItems,
                'bizmaps_preview_main'    => $mainResults,
                'bizmaps_preview_excl'    => [],
                'bizmaps_preview_exclsrc' => [],
                'bizmaps_preview_comp'    => [],
            ]);
            session()->save();

            $this->sseEmit([
                'scanned'    => $scannedTotal,
                'new_count'  => count($mainResults),
                'total'      => $limit,
                'finished'   => true,
                'main_count' => count($mainResults),
            ]);
            if (ob_get_level() > 0) ob_flush();
            flush();

        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * previewStreamで取得・セッション保存した結果を表示
     */
    public function previewResult()
    {
        $mainResults           = session('bizmaps_preview_main', []);
        $excludedResults       = session('bizmaps_preview_excl', []);
        $excludedSourceResults = session('bizmaps_preview_exclsrc', []);
        $companyExistedResults = session('bizmaps_preview_comp', []);
        $searchCondition       = session('bizmaps_search_condition', []);

        if (empty($mainResults) && empty($excludedResults)) {
            return redirect()->route('bizmaps.import');
        }

        $prefectureId = $searchCondition['prefecture_id'] ?? null;
        $prefecture   = $prefectureId ? DB::table('prefectures')->find($prefectureId) : null;
        $limit        = $searchCondition['limit'] ?? 50;
        $results      = $mainResults;
        $industries   = $this->getIndustries();

        return view('bizmaps.preview', compact(
            'mainResults', 'excludedResults', 'excludedSourceResults', 'companyExistedResults',
            'results', 'prefecture', 'limit', 'searchCondition', 'industries'
        ));
    }

    /**
     * SSEエンドポイント：1件ずつHP URLを取得してストリームで返す
     */
    public function fetchHpStream(Request $request): StreamedResponse
    {
        $items = session('bizmaps_detail_urls', []);
        // セッションロックを解放してブラウザの他リクエストをブロックしない
        session()->save();

        return new StreamedResponse(function () use ($items) {
            set_time_limit(0);
            if (ob_get_level() === 0) ob_start();

            $scraper = new BizmapsScraperService();
            $total   = count($items);
            $done    = 0;

            foreach ($items as $index => $info) {
                // 後方互換: 旧形式（文字列）と新形式（配列）両対応
                $detailUrl = is_string($info) ? $info : ($info['detail_url'] ?? null);
                $name      = is_string($info) ? null  : ($info['name']       ?? null);
                $done++;

                if (!$detailUrl) {
                    $this->sseEmit(['done' => $done, 'total' => $total, 'index' => $index, 'company_name' => $name, 'hp_url' => null, 'success' => false]);
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                    continue;
                }

                try {
                    $detail   = $scraper->fetchDetailInfo($detailUrl);
                    $hpUrl    = $detail['hp_url']   ?? null;
                    $industry = $detail['industry'] ?? null;
                    $this->sseEmit([
                        'done'         => $done,
                        'total'        => $total,
                        'index'        => $index,
                        'company_name' => $name,
                        'hp_url'       => $hpUrl,
                        'industry'     => $industry,
                        'success'      => $hpUrl !== null,
                    ]);
                } catch (\Throwable $e) {
                    $this->sseEmit(['done' => $done, 'total' => $total, 'index' => $index, 'company_name' => $name, 'hp_url' => null, 'success' => false]);
                }

                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            $this->sseEmit(['done' => $total, 'total' => $total, 'finished' => true]);
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
        $item      = $request->input('item', []);
        $detailUrl = $item['detail_url'] ?? null;
        if (!$detailUrl) return response()->json(['error' => 'no detail_url'], 400);

        DB::table('bizmaps_excluded_companies')->updateOrInsert(
            ['detail_url' => $detailUrl],
            [
                'name'        => $item['name'] ?? null,
                'pref'        => $item['pref'] ?? null,
                'city'        => $item['city'] ?? null,
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
                $normalizedDomain = UrlNormalizer::host($hpUrl);
            }

            DB::table('source_records')->insert([
                'source_type'       => 'bizmaps',
                'source_url'        => $sourceUrl,
                'normalized_domain' => $normalizedDomain,
                'name_norm'         => $this->normalizeName($item['name'] ?? null) ?: null,
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

    /**
     * BIZMAPSデータを直接companiesに保存
     */
    public function storeCompanies(Request $request)
    {
        $items   = $request->input('items', []);
        $saved   = 0;
        $skipped = 0;
        $now     = now();
        $mapper  = new BizmapsIndustryMapper();

        foreach ($items as $item) {
            $hpUrl     = $item['hp_url']     ?? null;
            $detailUrl = $item['detail_url'] ?? null;
            $name      = $item['name']       ?? null;
            if (!$name) { $skipped++; continue; }

            $checkUrl = $hpUrl ?: $detailUrl;
            if ($checkUrl && DB::table('domains')
                ->whereRaw("LOWER(TRIM(TRAILING '/' FROM url)) = ?", [$this->normalizeUrl($checkUrl)])
                ->exists()) {
                $skipped++;
                continue;
            }

            // 業種マッピング
            $industryText = $item['industry'] ?? null;
            $industryId   = null;
            if ($industryText) {
                $parts      = array_map('trim', explode(',', $industryText));
                $bigCat     = $parts[0] ?? '';
                $subCat     = $parts[1] ?? null;
                $industryId = $mapper->resolveId($bigCat, $subCat);
            }

            // municipality_id
            $municipalityId = null;
            if (!empty($item['pref']) && !empty($item['city'])) {
                $municipality   = DB::table('municipalities')->where('name', $item['city'])->first();
                $municipalityId = $municipality?->id;
            }

            // 正規化ドメイン
            $normalizedDomain = null;
            if ($hpUrl) {
                $normalizedDomain = UrlNormalizer::host($hpUrl);
            }

            DB::transaction(function () use (
                $item, $hpUrl, $detailUrl, $name, $industryId,
                $municipalityId, $normalizedDomain, $now, &$saved
            ) {
                $companyId = DB::table('companies')->insertGetId([
                    'status'            => 'candidate',
                    'municipality_id'   => $municipalityId,
                    'industry_id'       => $industryId,
                    'primary_domain_id' => null,
                    'legal_name'        => null,
                    'display_name'      => $name,
                    'name_norm'         => $this->normalizeName($name),
                    'alias_names_json'  => null,
                    'corporate_number'  => null,
                    'pref'              => $municipalityId ? null : ($item['pref'] ?? null),
                    'city'              => $municipalityId ? null : ($item['city'] ?? null),
                    'is_killed'         => false,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);

                if ($hpUrl) {
                    $domainId = DB::table('domains')->insertGetId([
                        'company_id'        => $companyId,
                        'url'               => $hpUrl,
                        'normalized_domain' => $normalizedDomain,
                        'role'              => 'official',
                        'is_primary'        => true,
                        'is_portal'         => false,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);
                    DB::table('companies')->where('id', $companyId)
                        ->update(['primary_domain_id' => $domainId]);
                }

                if ($detailUrl && !DB::table('source_records')->where('source_url', $detailUrl)->exists()) {
                    DB::table('source_records')->insert([
                        'source_type'       => 'bizmaps',
                        'source_url'        => $detailUrl,
                        'normalized_domain' => $normalizedDomain,
                        'name_norm'         => $this->normalizeName($name),
                        'pref'              => $item['pref'] ?? null,
                        'city'              => $item['city'] ?? null,
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
                }

                $saved++;
            });
        }

        return response()->json(['saved' => $saved, 'skipped' => $skipped]);
    }

    /**
     * チェックした行を is_excluded=true で保存、残りを company として保存
     */
    public function storeWithExclusion(Request $request)
    {
        $items              = $request->input('items', []);
        $excludedDetailUrls = $request->input('excluded_detail_urls', []);
        $excludedSet        = array_flip($excludedDetailUrls);
        $savedCompanies     = 0;
        $savedExcluded      = 0;
        $skipped            = 0;
        $now                = now();
        $mapper             = new BizmapsIndustryMapper();

        foreach ($items as $item) {
            if ($item['is_duplicate'] ?? false) {
                continue;
            }

            $hpUrl     = $item['hp_url']     ?? null;
            $detailUrl = $item['detail_url'] ?? null;
            $name      = $item['name']       ?? null;

            if (isset($excludedSet[$detailUrl])) {
                // is_excluded=true で source_record に保存
                $sourceUrl = $hpUrl ?: $detailUrl;
                if (!$sourceUrl) { $skipped++; continue; }

                $normalizedDomain = null;
                if ($hpUrl) {
                    $normalizedDomain = UrlNormalizer::host($hpUrl);
                }

                $existing = DB::table('source_records')->where('source_url', $sourceUrl)->exists();
                if ($existing) {
                    DB::table('source_records')
                        ->where('source_url', $sourceUrl)
                        ->update(['is_excluded' => true, 'updated_at' => $now]);
                } else {
                    DB::table('source_records')->insert([
                        'source_type'       => 'bizmaps',
                        'source_url'        => $sourceUrl,
                        'normalized_domain' => $normalizedDomain,
                        'name_norm'         => $this->normalizeName($name) ?: null,
                        'pref'              => $item['pref'] ?? null,
                        'city'              => $item['city'] ?? null,
                        'raw_json'          => json_encode([
                            'hp_url'     => $hpUrl,
                            'detail_url' => $detailUrl,
                            'industry'   => $item['industry'] ?? null,
                            'source'     => 'bizmaps',
                        ], JSON_UNESCAPED_UNICODE),
                        'is_excluded' => true,
                        'fetched_at'  => $now,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                }
                $savedExcluded++;
            } else {
                // 通常の company 作成（storeCompanies と同じロジック）
                if (!$name) { $skipped++; continue; }

                $checkUrl = $hpUrl ?: $detailUrl;
                if ($checkUrl && DB::table('domains')
                    ->whereRaw("LOWER(TRIM(TRAILING '/' FROM url)) = ?", [$this->normalizeUrl($checkUrl)])
                    ->exists()) {
                    $skipped++;
                    continue;
                }

                $industryText = $item['industry'] ?? null;
                $industryId   = null;
                if ($industryText) {
                    $parts      = array_map('trim', explode(',', $industryText));
                    $bigCat     = $parts[0] ?? '';
                    $subCat     = $parts[1] ?? null;
                    $industryId = $mapper->resolveId($bigCat, $subCat);
                }

                $municipalityId = null;
                if (!empty($item['pref']) && !empty($item['city'])) {
                    $municipality   = DB::table('municipalities')->where('name', $item['city'])->first();
                    $municipalityId = $municipality?->id;
                }

                $normalizedDomain = null;
                if ($hpUrl) {
                    $normalizedDomain = UrlNormalizer::host($hpUrl);
                }

                DB::transaction(function () use (
                    $item, $hpUrl, $detailUrl, $name, $industryId,
                    $municipalityId, $normalizedDomain, $now, &$savedCompanies
                ) {
                    $companyId = DB::table('companies')->insertGetId([
                        'status'            => 'candidate',
                        'municipality_id'   => $municipalityId,
                        'industry_id'       => $industryId,
                        'primary_domain_id' => null,
                        'legal_name'        => null,
                        'display_name'      => $name,
                        'name_norm'         => $this->normalizeName($name),
                        'alias_names_json'  => null,
                        'corporate_number'  => null,
                        'pref'              => $municipalityId ? null : ($item['pref'] ?? null),
                        'city'              => $municipalityId ? null : ($item['city'] ?? null),
                        'is_killed'         => false,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);

                    if ($hpUrl) {
                        $domainId = DB::table('domains')->insertGetId([
                            'company_id'        => $companyId,
                            'url'               => $hpUrl,
                            'normalized_domain' => $normalizedDomain,
                            'role'              => 'official',
                            'is_primary'        => true,
                            'is_portal'         => false,
                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ]);
                        DB::table('companies')->where('id', $companyId)
                            ->update(['primary_domain_id' => $domainId]);
                    }

                    if ($detailUrl && !DB::table('source_records')->where('source_url', $detailUrl)->exists()) {
                        DB::table('source_records')->insert([
                            'source_type'       => 'bizmaps',
                            'source_url'        => $detailUrl,
                            'normalized_domain' => $normalizedDomain,
                            'name_norm'         => $this->normalizeName($name),
                            'pref'              => $item['pref'] ?? null,
                            'city'              => $item['city'] ?? null,
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
                    }

                    $savedCompanies++;
                });
            }
        }

        return response()->json([
            'saved_companies' => $savedCompanies,
            'saved_excluded'  => $savedExcluded,
            'skipped'         => $skipped,
        ]);
    }

    /**
     * チェックした行をカンパニー化、未チェック行を is_excluded=true で保存
     */
    public function storeWithExclusionAll(Request $request)
    {
        $storeItems   = $request->input('store_items', []);
        $excludeItems = $request->input('exclude_items', []);
        $savedCompanies = 0;
        $savedExcluded  = 0;
        $skipped        = 0;
        $now            = now();
        $mapper         = new BizmapsIndustryMapper();

        foreach ($storeItems as $item) {
            if ($item['is_duplicate'] ?? false) { $skipped++; continue; }

            $hpUrl     = $item['hp_url']     ?? null;
            $detailUrl = $item['detail_url'] ?? null;
            $name      = $item['name']       ?? null;
            if (!$name) { $skipped++; continue; }

            $checkUrl = $hpUrl ?: $detailUrl;
            if ($checkUrl && DB::table('domains')
                ->whereRaw("LOWER(TRIM(TRAILING '/' FROM url)) = ?", [$this->normalizeUrl($checkUrl)])
                ->exists()) {
                $skipped++;
                continue;
            }

            $industryText = $item['industry'] ?? null;
            $industryId   = null;
            if ($industryText) {
                $parts      = array_map('trim', explode(',', $industryText));
                $bigCat     = $parts[0] ?? '';
                $subCat     = $parts[1] ?? null;
                $industryId = $mapper->resolveId($bigCat, $subCat);
            }

            $municipalityId = null;
            if (!empty($item['pref']) && !empty($item['city'])) {
                $municipality   = DB::table('municipalities')->where('name', $item['city'])->first();
                $municipalityId = $municipality?->id;
            }

            $normalizedDomain = null;
            if ($hpUrl) {
                $normalizedDomain = UrlNormalizer::host($hpUrl);
            }

            DB::transaction(function () use (
                $item, $hpUrl, $detailUrl, $name, $industryId,
                $municipalityId, $normalizedDomain, $now, &$savedCompanies
            ) {
                $companyId = DB::table('companies')->insertGetId([
                    'status'            => 'candidate',
                    'municipality_id'   => $municipalityId,
                    'industry_id'       => $industryId,
                    'primary_domain_id' => null,
                    'legal_name'        => null,
                    'display_name'      => $name,
                    'name_norm'         => $this->normalizeName($name),
                    'alias_names_json'  => null,
                    'corporate_number'  => null,
                    'pref'              => $municipalityId ? null : ($item['pref'] ?? null),
                    'city'              => $municipalityId ? null : ($item['city'] ?? null),
                    'is_killed'         => false,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);

                if ($hpUrl) {
                    $domainId = DB::table('domains')->insertGetId([
                        'company_id'        => $companyId,
                        'url'               => $hpUrl,
                        'normalized_domain' => $normalizedDomain,
                        'role'              => 'official',
                        'is_primary'        => true,
                        'is_portal'         => false,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);
                    DB::table('companies')->where('id', $companyId)
                        ->update(['primary_domain_id' => $domainId]);
                }

                if ($detailUrl && !DB::table('source_records')->where('source_url', $detailUrl)->exists()) {
                    DB::table('source_records')->insert([
                        'source_type'       => 'bizmaps',
                        'source_url'        => $detailUrl,
                        'normalized_domain' => $normalizedDomain,
                        'name_norm'         => $this->normalizeName($name),
                        'pref'              => $item['pref'] ?? null,
                        'city'              => $item['city'] ?? null,
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
                }

                $savedCompanies++;
            });
        }

        foreach ($excludeItems as $item) {
            $hpUrl     = $item['hp_url']     ?? null;
            $detailUrl = $item['detail_url'] ?? null;
            if (!$detailUrl) { $skipped++; continue; }

            $normalizedDomain = $hpUrl ? UrlNormalizer::host($hpUrl) : null;

            // SSEフィルター用にbizmaps_excluded_companiesへ登録
            DB::table('bizmaps_excluded_companies')->updateOrInsert(
                ['detail_url' => $detailUrl],
                [
                    'name'        => $item['name'] ?? null,
                    'pref'        => $item['pref'] ?? null,
                    'city'        => $item['city'] ?? null,
                    'excluded_at' => $now,
                    'updated_at'  => $now,
                    'created_at'  => $now,
                ]
            );

            // detail_urlをキーにsource_recordsへis_excluded=trueで登録
            $existing = DB::table('source_records')->where('source_url', $detailUrl)->exists();
            if ($existing) {
                DB::table('source_records')
                    ->where('source_url', $detailUrl)
                    ->update(['is_excluded' => true, 'updated_at' => $now]);
            } else {
                DB::table('source_records')->insert([
                    'source_type'       => 'bizmaps',
                    'source_url'        => $detailUrl,
                    'normalized_domain' => $normalizedDomain,
                    'name_norm'         => $this->normalizeName($item['name'] ?? null) ?: null,
                    'pref'              => $item['pref'] ?? null,
                    'city'              => $item['city'] ?? null,
                    'raw_json'          => json_encode([
                        'hp_url'     => $hpUrl,
                        'detail_url' => $detailUrl,
                        'industry'   => $item['industry'] ?? null,
                        'source'     => 'bizmaps',
                    ], JSON_UNESCAPED_UNICODE),
                    'is_excluded' => true,
                    'fetched_at'  => $now,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
            $savedExcluded++;
        }

        return response()->json([
            'saved_companies' => $savedCompanies,
            'saved_excluded'  => $savedExcluded,
            'skipped'         => $skipped,
        ]);
    }

    private function normalizeUrl(?string $url): string
    {
        return UrlNormalizer::normalize($url);
    }

    private function normalizeName(?string $name): string
    {
        return NameNormalizer::normalize($name);
    }

    private function matchesExistingCompany(array $r, array $existingByName): bool
    {
        $normName = $this->normalizeName($r['name'] ?? '');
        if (!$normName || !isset($existingByName[$normName])) return false;
        $bizmapsCity = $r['city'] ?? '';
        foreach ($existingByName[$normName] as $companyCity) {
            if ($bizmapsCity === $companyCity) return true;
            if ($bizmapsCity !== '' && $companyCity !== '' &&
                (str_contains($bizmapsCity, $companyCity) || str_contains($companyCity, $bizmapsCity))) {
                return true;
            }
        }
        return false;
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
