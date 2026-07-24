<?php

namespace App\Services\ThemeWizard;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the "from reference" path (W2): capture → vision analysis →
 * compile to a candidate theme.json. Returns both the candidate theme document
 * and the profile/design-read so the wizard UI can show the token summary and
 * a live preview (via the existing studioFrame) before the user accepts.
 */
class ReferenceThemeService
{
    public function __construct(
        private ReferenceCaptureService $capture,
        private ThemeVisionAnalyzer $analyzer,
        private TokenProfileCompiler $compiler,
    ) {}

    /**
     * @return array{profile:array, compiled:array, usages:array<int,array>}
     */
    public function fromUrl(string $tenantId, string $url, ?string $hint = null): array
    {
        $image = $this->capture->fromUrl($url);

        // A phone-viewport capture makes the responsive read (type scale,
        // density) far more reliable — but its absence must never sink the
        // wizard, so failures degrade to the desktop-only analysis.
        $extraImages = [];
        try {
            $extraImages[] = $this->capture->fromUrl($url, false, '390x844');
        } catch (\Throwable $e) {
            Log::info('ThemeWizard: mobile capture skipped', ['url' => $url, 'err' => mb_substr($e->getMessage(), 0, 120)]);
        }

        return $this->analyzeAndCompile($tenantId, $image, $hint, $extraImages);
    }

    /**
     * @return array{profile:array, compiled:array, usages:array<int,array>}
     */
    public function fromUpload(string $tenantId, UploadedFile $file, ?string $hint = null): array
    {
        $image = $this->capture->fromUpload($file);
        return $this->analyzeAndCompile($tenantId, $image, $hint);
    }

    /**
     * @param array{data:string, media_type:string} $image
     * @return array{profile:array, compiled:array, usages:array<int,array>}
     */
    private function analyzeAndCompile(string $tenantId, array $image, ?string $hint, array $extraImages = []): array
    {
        $result = $this->analyzer->analyze($tenantId, $image['data'], $image['media_type'], $hint, $extraImages);
        $compiled = $this->compiler->compile($result['profile']);

        return [
            'profile' => $result['profile'],
            'compiled' => $compiled,
            'usages' => $result['usages'],
        ];
    }
}
