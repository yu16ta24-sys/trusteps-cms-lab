<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;

class HtmlFetchService
{
    public function fetch(string $url): array
    {
        $response = Http::withHeaders([
                'User-Agent' => 'TRUSTEPS-CMS-Lab/0.20.0 (+directory-source-research; contact: trusteps.jp)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->timeout(15)
            ->connectTimeout(8)
            ->retry(1, 300)
            ->get($url);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'html' => '',
                'error' => 'HTTP '.$response->status(),
            ];
        }

        $body = $response->body();
        $html = $this->toUtf8($body, $response->header('Content-Type'));

        return [
            'ok' => true,
            'status' => $response->status(),
            'html' => $html,
            'error' => null,
        ];
    }

    private function toUtf8(string $html, ?string $contentType = null): string
    {
        $encoding = null;
        if ($contentType && preg_match('/charset=([a-zA-Z0-9_\-]+)/i', $contentType, $m)) {
            $encoding = strtoupper($m[1]);
        }
        if (! $encoding && preg_match('/<meta[^>]+charset=["\']?([^"\'\s>]+)/i', $html, $m)) {
            $encoding = strtoupper($m[1]);
        }
        if (! $encoding) {
            $encoding = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'CP932', 'EUC-JP', 'ISO-2022-JP'], true) ?: 'UTF-8';
        }
        if (strtoupper($encoding) !== 'UTF-8') {
            $converted = @mb_convert_encoding($html, 'UTF-8', $encoding);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        return $html;
    }
}
