<?php

namespace App\Domain\Forms\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FormSubmissionService
{
    public function submit(string $siteId, array $data, string $recipientEmail): bool
    {
        // Honeypot check
        if (!empty($data['_honeypot'])) {
            Log::info('Form submission blocked by honeypot', ['site_id' => $siteId]);
            return true;
        }

        unset($data['_honeypot'], $data['_token']);

        // Store submission to JSON file
        $this->storeSubmission($siteId, $data);

        try {
            Mail::raw($this->formatEmail($data), function ($message) use ($recipientEmail) {
                $message->to($recipientEmail)
                    ->subject('New Contact Form Submission');
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Form email failed', ['error' => $e->getMessage(), 'site_id' => $siteId]);
            // Still return true — submission was stored even if email failed
            return true;
        }
    }

    /**
     * Get all submissions for a site.
     */
    public function getSubmissions(string $siteId, int $limit = 50): array
    {
        $path = $this->submissionsPath($siteId);
        if (!file_exists($path)) return [];

        $submissions = json_decode(file_get_contents($path), true) ?: [];
        return array_slice(array_reverse($submissions), 0, $limit);
    }

    /**
     * Delete a submission by index.
     */
    public function deleteSubmission(string $siteId, int $index): bool
    {
        $path = $this->submissionsPath($siteId);
        if (!file_exists($path)) return false;

        $submissions = json_decode(file_get_contents($path), true) ?: [];
        $reversed = array_reverse($submissions);
        if (!isset($reversed[$index])) return false;

        // Remove from original array
        $originalIndex = count($submissions) - 1 - $index;
        array_splice($submissions, $originalIndex, 1);
        file_put_contents($path, json_encode($submissions, JSON_PRETTY_PRINT));
        return true;
    }

    private function storeSubmission(string $siteId, array $data): void
    {
        $path = $this->submissionsPath($siteId);
        File::ensureDirectoryExists(dirname($path));

        $submissions = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $submissions[] = [
            'id' => uniqid('sub_'),
            'data' => $data,
            'submitted_at' => now()->toIso8601String(),
            'ip' => request()->ip(),
        ];

        // Keep max 500 submissions per site
        if (count($submissions) > 500) {
            $submissions = array_slice($submissions, -500);
        }

        file_put_contents($path, json_encode($submissions, JSON_PRETTY_PRINT));
    }

    private function submissionsPath(string $siteId): string
    {
        $safeSiteId = preg_replace('/[^a-f0-9\-]/', '', $siteId);
        return storage_path("app/form-submissions/{$safeSiteId}.json");
    }

    private function formatEmail(array $data): string
    {
        $lines = ["New form submission:\n"];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) continue;
            $label = ucfirst(str_replace(['-', '_'], ' ', $key));
            $lines[] = "{$label}: {$value}";
        }

        return implode("\n", $lines);
    }
}
