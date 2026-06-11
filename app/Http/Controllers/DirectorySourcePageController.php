<?php

namespace App\Http\Controllers;

use App\Models\DirectorySource;
use App\Models\DirectorySourcePage;
use App\Models\ExtractedBusinessCandidate;
use App\Services\Discovery\BusinessCandidateSaverService;
use App\Services\Discovery\HtmlFetchService;
use App\Services\Discovery\MemberListParserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DirectorySourcePageController extends Controller
{
    public function index(DirectorySource $directorySource): View
    {
        $pages = $directorySource->pages()
            ->withCount('extractedBusinessCandidates')
            ->orderByRaw("CASE WHEN page_type IN ('member_list','member_list_candidate','member_list_candidate_deep') THEN 0 ELSE 1 END")
            ->orderByDesc('score')
            ->orderBy('id')
            ->get();

        return view('directory-sources.pages', compact('directorySource', 'pages'));
    }

    public function extract(
        Request $request,
        DirectorySourcePage $page,
        HtmlFetchService $fetcher,
        MemberListParserService $parser
    ): View|RedirectResponse {
        if (! Schema::hasTable('extracted_business_candidates')) {
            return redirect()
                ->route('directory-sources.show', $page->directory_source_id)
                ->withErrors(['setup' => 'extracted_business_candidates テーブルが未作成。SSHで php artisan migrate --force を実行して。']);
        }

        $page->load('directorySource');
        $force = $request->boolean('force');

        if ($force || $page->extractedBusinessCandidates()->where('save_status', 'pending')->count() === 0) {
            $result = $fetcher->fetch($page->url);
            if (! $result['ok']) {
                $page->forceFill([
                    'fetch_error' => $result['error'],
                    'last_fetched_at' => now(),
                ])->save();

                return redirect()
                    ->route('directory-sources.show', $page->directory_source_id)
                    ->withErrors(['fetch' => '候補ページの取得に失敗：'.$result['error']]);
            }

            $businesses = $parser->extractBusinesses($result['html'], $page->url, (string) $page->directorySource?->url);

            DB::transaction(function () use ($page, $businesses): void {
                $page->extractedBusinessCandidates()
                    ->where('save_status', 'pending')
                    ->whereNull('source_record_id')
                    ->delete();

                foreach ($businesses as $business) {
                    ExtractedBusinessCandidate::create(array_merge($business, [
                        'directory_source_page_id' => $page->id,
                        'directory_source_id' => $page->directory_source_id,
                        'save_status' => 'pending',
                    ]));
                }

                $page->forceFill([
                    'extraction_status' => count($businesses) > 0 ? 'extracted' : 'no_candidate',
                    'last_fetched_at' => now(),
                    'fetch_error' => null,
                ])->save();
            });
        }

        $page->load(['directorySource', 'extractedBusinessCandidates' => fn ($query) => $query->orderByDesc('url_confidence')->orderBy('id')]);

        $stats = [
            'total' => $page->extractedBusinessCandidates->count(),
            'with_url' => $page->extractedBusinessCandidates->whereNotNull('url_candidate')->count(),
            'saved' => $page->extractedBusinessCandidates->where('save_status', 'saved')->count(),
            'pending' => $page->extractedBusinessCandidates->where('save_status', 'pending')->count(),
        ];

        return view('directory-sources.extract', compact('page', 'stats'));
    }

    public function saveCandidates(
        Request $request,
        DirectorySourcePage $page,
        BusinessCandidateSaverService $saver
    ): RedirectResponse {
        $ids = (array) $request->input('candidate_ids', []);
        if (empty($ids)) {
            return redirect()
                ->route('directory-source-pages.extract', $page)
                ->withErrors(['candidate_ids' => 'source_recordsへ保存する事業者候補を1件以上選んで。']);
        }

        $result = $saver->saveToSourceRecords($ids);

        return redirect()
            ->route('directory-source-pages.extract', $page)
            ->with('status', "source_recordsへ {$result['saved']} 件保存。スキップ {$result['skipped']} 件、重複 {$result['duplicates']} 件、URLなし {$result['noUrl']} 件。");
    }
}
