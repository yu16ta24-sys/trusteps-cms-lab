<?php

namespace App\Services\Discovery;

use App\Models\ExtractedBusinessCandidate;
use App\Models\SourceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessCandidateSaverService
{
    public function saveToSourceRecords(array $candidateIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $candidateIds)));
        $saved = 0;
        $skipped = 0;
        $duplicates = 0;
        $noUrl = 0;

        DB::transaction(function () use ($ids, &$saved, &$skipped, &$duplicates, &$noUrl): void {
            $candidates = ExtractedBusinessCandidate::query()
                ->with(['directorySource', 'directorySourcePage'])
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($candidates as $candidate) {
                $url = trim((string) $candidate->url_candidate);
                if ($url === '') {
                    $candidate->update(['save_status' => 'skipped']);
                    $noUrl++;
                    $skipped++;
                    continue;
                }

                $existsQuery = SourceRecord::query()->where('source_url', $url);
                if ($candidate->normalized_domain) {
                    $existsQuery->orWhere(function ($query) use ($candidate) {
                        $query->where('normalized_domain', $candidate->normalized_domain)
                            ->where('source_type', '!=', 'directory_source_candidate');
                    });
                }
                $exists = $existsQuery->exists();

                if ($exists) {
                    $candidate->update(['save_status' => 'duplicate']);
                    $duplicates++;
                    $skipped++;
                    continue;
                }

                $source = $candidate->directorySource;
                $page = $candidate->directorySourcePage;

                $record = SourceRecord::create([
                    'source_type' => 'directory_extracted_business_candidate',
                    'source_url' => $url,
                    'raw_json' => [
                        'origin' => 'directory_member_list_parser',
                        'created_version' => '0.20.0',
                        'extracted_business_candidate_id' => $candidate->id,
                        'directory_source_id' => $candidate->directory_source_id,
                        'directory_source_name' => $source?->name,
                        'directory_source_url' => $source?->url,
                        'directory_source_pref_code' => $source?->pref_code,
                        'directory_source_pref_name' => $source?->pref_name,
                        'directory_source_page_id' => $candidate->directory_source_page_id,
                        'directory_source_page_url' => $page?->url,
                        'business_name' => $candidate->business_name,
                        'address' => $candidate->address,
                        'tel' => $candidate->tel,
                        'fax' => $candidate->fax,
                        'business_type' => $candidate->business_type,
                        'url_type' => $candidate->url_type,
                        'url_confidence' => $candidate->url_confidence,
                        'sns_urls' => $candidate->sns_urls,
                        'detail_page_url' => $candidate->detail_page_url,
                        'extraction_method' => $candidate->extraction_method,
                    ],
                    'normalized_domain' => $candidate->normalized_domain,
                    'normalized_phone' => $candidate->tel,
                    'name_norm' => Str::limit((string) ($candidate->business_name ?: $candidate->normalized_domain ?: $url), 255, ''),
                    'pref' => $source?->pref_name,
                    'city' => $source?->city,
                    'fetched_at' => now(),
                ]);

                $candidate->update([
                    'save_status' => 'saved',
                    'source_record_id' => $record->id,
                ]);
                $saved++;
            }
        });

        return compact('saved', 'skipped', 'duplicates', 'noUrl');
    }
}
