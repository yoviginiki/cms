<?php

namespace App\Domain\Forms\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FormSubmissionService
{
    public function submit(string $siteId, array $data, string $recipientEmail): bool
    {
        // Honeypot check
        if (!empty($data['_honeypot'])) {
            Log::info('Form submission blocked by honeypot', ['site_id' => $siteId]);
            return true; // Return true to not reveal the check
        }

        unset($data['_honeypot'], $data['_token']);

        try {
            Mail::raw($this->formatEmail($data), function ($message) use ($recipientEmail) {
                $message->to($recipientEmail)
                    ->subject('New Contact Form Submission');
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Form email failed', ['error' => $e->getMessage(), 'site_id' => $siteId]);
            return false;
        }
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
