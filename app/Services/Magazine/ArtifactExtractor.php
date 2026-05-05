<?php

namespace App\Services\Magazine;

use Illuminate\Support\Facades\Log;

class ArtifactExtractor
{
    public static function extract(string $text): ?array
    {
        if (!preg_match('/<artifact_update>(.*?)<\/artifact_update>/s', $text, $matches)) {
            return null;
        }

        $raw = trim($matches[1]);

        if ($raw === 'null' || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('WizardArtifactExtractor: malformed JSON in artifact_update', [
                'error' => json_last_error_msg(),
                'raw' => mb_substr($raw, 0, 500),
            ]);
            return null;
        }

        return $decoded;
    }
}
