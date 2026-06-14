<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Log an activity. Fails silently to never block primary actions.
     */
    public function log(
        string $action,
        ?string $siteId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $metadata = null,
    ): ?ActivityLog {
        try {
            return ActivityLog::create([
                'site_id' => $siteId,
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'metadata' => $metadata ? $this->sanitizeMetadata($metadata) : null,
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // Never fail the primary action due to logging
            report($e);
            return null;
        }
    }

    /**
     * Remove sensitive data from metadata before storing.
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $forbidden = ['password', 'api_key', 'secret', 'token', 'credential'];
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $lowerKey = strtolower($key);
            $isForbidden = false;
            foreach ($forbidden as $word) {
                if (str_contains($lowerKey, $word)) {
                    $isForbidden = true;
                    break;
                }
            }
            if ($isForbidden) continue;

            // Limit value size
            if (is_string($value) && strlen($value) > 500) {
                $value = substr($value, 0, 500) . '...';
            }
            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
