<?php

namespace App\Http\Controllers;

use App\Models\DirectorySource;
use App\Models\SourceRecord;
use App\Services\Discovery\DirectorySourceCrawlerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DirectorySourceController extends Controller
{
    private const DIRECTORY_SOURCE_TYPES = ['directory_source_candidate'];

    public function __construct(
        private readonly DirectorySourceCrawlerService $crawlerService
    ) {
    }

    public function index(Request $request): View
    {
        if (! $this->hasDirectorySourceTables()) {
            $directorySources = new LengthAwarePaginator([], 0, 50);
            $directorySources->setPath($request->url());

            $stats = [
                'total' => 0,
                'not_crawled' => 0,
                'candidate_found' => 0,
                'no_candidate' => 0,
                'fetch_error' => 0,
                'pages' => 0,
                'unregistered_source_records' => 0,
            ];
            $prefOptions = collect();
            $setupRequired = true;

            return view('directory-sources.index', compact('directorySources', 'stats', 'prefOptions', 'setupRequired'));
        }

        $setupRequired = false;

        $query = DirectorySource::query()
            ->withCount(['pages', 'candidatePages'])
            ->latest('id');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('url', 'like', "%{$q}%")
                    ->orWhere('normalized_domain', 'like', "%{$q}%")
                    ->orWhere('pref_name', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });
        }

        if ($request->filled('pref_name')) {
            $query->where('pref_name', (string) $request->input('pref_name'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('crawl_status')) {
            $query->where('crawl_status', (string) $request->input('crawl_status'));
        }

        $directorySources = $query->paginate(50)->withQueryString();

        $stats = [
            'total' => DirectorySource::count(),
            'not_crawled' => DirectorySource::where('crawl_status', 'not_crawled')->count(),
            'candidate_found' => DirectorySource::where('crawl_status', 'candidate_found')->count(),
            'no_candidate' => DirectorySource::where('crawl_status', 'no_candidate')->count(),
            'fetch_error' => DirectorySource::where('crawl_status', 'fetch_error')->count(),
            'pages' => DB::table('directory_source_pages')->count(),
            'unregistered_source_records' => $this->unregisteredSourceRecordsQuery()->count(),
        ];

        $prefOptions = DirectorySource::query()
            ->whereNotNull('pref_name')
            ->distinct()
            ->orderBy('pref_name')
            ->pluck('pref_name')
            ->values();

        return view('directory-sources.index', compact('directorySources', 'stats', 'prefOptions', 'setupRequired'));
    }

    public function show(DirectorySource $directorySource): View
    {
        $directorySource->load(['sourceRecord', 'pages' => fn ($query) => $query->orderByDesc('score')->orderBy('id')]);

        return view('directory-sources.show', compact('directorySource'));
    }

    public function importFromSourceRecords(Request $request): RedirectResponse
    {
        if (! $this->hasDirectorySourceTables()) {
            return redirect()->route('directory-sources.index')->withErrors(['setup' => 'directory_sources テーブルが未作成。SSHで php artisan migrate --force を実行して。']);
        }

        $limit = (int) $request->input('limit', 3000);
        $limit = max(1, min(5000, $limit));

        $records = $this->unregisteredSourceRecordsQuery()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $created = 0;
        $skippedNoUrl = 0;

        DB::transaction(function () use ($records, &$created, &$skippedNoUrl): void {
            foreach ($records as $record) {
                $raw = is_array($record->raw_json) ? $record->raw_json : [];
                $url = trim((string) ($record->source_url ?: data_get($raw, 'url')));
                $name = trim((string) (data_get($raw, 'organization_name') ?: $record->name_norm ?: '名称未取得'));

                DirectorySource::create([
                    'source_record_id' => $record->id,
                    'source_type' => $record->source_type,
                    'name' => Str::limit($name, 255, ''),
                    'url' => $url !== '' ? $url : null,
                    'normalized_domain' => $record->normalized_domain,
                    'pref_code' => data_get($raw, 'pref_code'),
                    'pref_name' => data_get($raw, 'pref_label') ?: $record->pref,
                    'city' => $record->city,
                    'organization_type' => data_get($raw, 'organization_type'),
                    'status' => $url !== '' ? 'active' : 'no_url',
                    'crawl_status' => $url !== '' ? 'not_crawled' : 'no_url',
                    'raw_json' => [
                        'created_from' => 'source_record',
                        'created_version' => '0.18.9.8',
                        'source_record_id' => $record->id,
                        'source_record_raw' => $raw,
                    ],
                ]);

                if ($url === '') {
                    $skippedNoUrl++;
                }
                $created++;
            }
        });

        return redirect()
            ->route('directory-sources.index')
            ->with('status', "未登録の名簿元source_recordsから directory_sources を {$created} 件作成した。URLなし {$skippedNoUrl} 件は探索不可として保持。");
    }

    public function crawlOne(Request $request, DirectorySource $directorySource): RedirectResponse
    {
        $limit = (int) $request->input('limit', 50);
        $result = $this->crawlerService->crawl($directorySource, $limit);

        return redirect()
            ->route('directory-sources.show', $directorySource)
            ->with('status', $result['message'] ?? '探索を実行した。');
    }

    public function crawlSelected(Request $request): RedirectResponse
    {
        if (! $this->hasDirectorySourceTables()) {
            return redirect()->route('directory-sources.index')->withErrors(['setup' => 'directory_sources テーブルが未作成。SSHで php artisan migrate --force を実行して。']);
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('directory_source_ids', []))));
        if (empty($ids)) {
            return redirect()
                ->route('directory-sources.index', $request->except(['_token', 'directory_source_ids']))
                ->withErrors(['directory_source_ids' => '探索する名簿元を1件以上選んで。']);
        }

        $limitPerSource = (int) $request->input('limit_per_source', 50);
        $sources = DirectorySource::query()
            ->whereIn('id', $ids)
            ->limit(30)
            ->get();

        $crawled = 0;
        $found = 0;
        foreach ($sources as $source) {
            $result = $this->crawlerService->crawl($source, $limitPerSource);
            $crawled++;
            $found += (int) ($result['created_or_updated'] ?? 0);
        }

        return redirect()
            ->route('directory-sources.index', $request->except(['_token', 'directory_source_ids', 'limit_per_source']))
            ->with('status', "選択した名簿元 {$crawled} 件を探索し、会員一覧候補を {$found} 件登録/更新した。");
    }

    public function crawlQueue(Request $request): RedirectResponse
    {
        if (! $this->hasDirectorySourceTables()) {
            return redirect()->route('directory-sources.index')->withErrors(['setup' => 'directory_sources テーブルが未作成。SSHで php artisan migrate --force を実行して。']);
        }

        $queueLimit = (int) $request->input('queue_limit', 10);
        $queueLimit = max(1, min(50, $queueLimit));
        $limitPerSource = (int) $request->input('limit_per_source', 50);

        $sources = DirectorySource::query()
            ->where('status', 'active')
            ->where('crawl_status', 'not_crawled')
            ->whereNotNull('url')
            ->orderBy('id')
            ->limit($queueLimit)
            ->get();

        $crawled = 0;
        $found = 0;
        foreach ($sources as $source) {
            $result = $this->crawlerService->crawl($source, $limitPerSource);
            $crawled++;
            $found += (int) ($result['created_or_updated'] ?? 0);
        }

        return redirect()
            ->route('directory-sources.index')
            ->with('status', "未探索キューから {$crawled} 件を探索し、会員一覧候補を {$found} 件登録/更新した。");
    }

    private function hasDirectorySourceTables(): bool
    {
        return Schema::hasTable('directory_sources') && Schema::hasTable('directory_source_pages');
    }

    private function unregisteredSourceRecordsQuery()
    {
        return SourceRecord::query()
            ->whereIn('source_type', self::DIRECTORY_SOURCE_TYPES)
            ->whereDoesntHave('directorySource');
    }
}
