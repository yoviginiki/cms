<?php

namespace App\Domain\Import\Services;

use App\Domain\Assets\Services\AssetService;
use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttachmentImporter
{
    public function __construct(private AssetService $assetService)
    {
    }

    /**
     * Import WordPress attachments, downloading and re-uploading through AssetService.
     *
     * @return array<int, string> Map of wordpress_attachment_id => cms_asset_id
     */
    public function importAttachments(Site $site, array $attachments, string $baseUrl): array
    {
        $map = [];

        foreach ($attachments as $attachment) {
            $wpId = (int) ($attachment['wp_id'] ?? 0);
            if (!$wpId) {
                continue;
            }

            $url = $attachment['url'] ?? '';
            if (empty($url)) {
                continue;
            }

            try {
                $asset = $this->downloadAndUpload($site, $url, $baseUrl);
                if ($asset) {
                    $map[$wpId] = $asset->id;
                }
            } catch (\Throwable $e) {
                Log::warning("WordPress import: Failed to download attachment {$wpId} from {$url}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $map;
    }

    /**
     * Download a file from URL and upload through AssetService.
     */
    private function downloadAndUpload(Site $site, string $url, string $baseUrl): ?Asset
    {
        // Try downloading with retries
        $response = $this->downloadWithRetry($url);

        // If primary URL fails, try to reconstruct from relative path
        if (!$response && $baseUrl) {
            $path = parse_url($url, PHP_URL_PATH);
            if ($path) {
                $altUrl = rtrim($baseUrl, '/') . $path;
                if ($altUrl !== $url) {
                    $response = $this->downloadWithRetry($altUrl);
                }
            }
        }

        if (!$response) {
            return null;
        }

        // Create a temporary file
        $filename = basename(parse_url($url, PHP_URL_PATH) ?: 'download');
        $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '_' . $filename;
        file_put_contents($tempPath, $response);

        try {
            $uploadedFile = new UploadedFile(
                $tempPath,
                $filename,
                mime_content_type($tempPath) ?: 'application/octet-stream',
                null,
                true
            );

            return $this->assetService->upload($site, $uploadedFile);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Download a URL with retry logic.
     */
    private function downloadWithRetry(string $url, int $maxRetries = 2): ?string
    {
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout(30)
                    ->connectTimeout(10)
                    ->withUserAgent('CMS-Importer/1.0')
                    ->get($url);

                if ($response->successful()) {
                    return $response->body();
                }
            } catch (\Throwable) {
                if ($attempt < $maxRetries) {
                    usleep(500000 * ($attempt + 1)); // 0.5s, 1s backoff
                }
            }
        }

        return null;
    }
}
