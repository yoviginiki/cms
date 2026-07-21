<?php

namespace App\Domain\Forms\Services;

use App\Models\FormSubmission;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * S5 Forms v2 — schema-validated, DB-backed form submissions.
 *
 * The field schema always comes from the FORM BLOCK stored server-side —
 * the request only supplies values, so a forged POST can't invent fields.
 * Spam walls: honeypot (filled → pretend success, store nothing) and a
 * JS-set time trap (`_t` = epoch ms at page load; present-but-too-fast →
 * pretend success; absent → allowed, because no-JS visitors are legit).
 */
class FormSubmissionService
{
    private const MIN_FILL_MS = 3000;

    /**
     * @param array<int, array{name: string, label: string, type: string, required: bool, options?: array}> $schemaFields
     * @return array{stored: bool, submission: ?FormSubmission}
     */
    public function submit(Site $site, string $formKey, array $schemaFields, array $input, ?string $notifyEmail, array $meta = []): array
    {
        if (!empty($input['_honeypot'])) {
            Log::info('Form submission blocked by honeypot', ['site_id' => $site->id, 'form_key' => $formKey]);

            return ['stored' => false, 'submission' => null];
        }
        if ($this->tooFast($input)) {
            Log::info('Form submission blocked by time trap', ['site_id' => $site->id, 'form_key' => $formKey]);

            return ['stored' => false, 'submission' => null];
        }

        $data = $this->validate($schemaFields, $input);

        $submission = FormSubmission::create([
            'site_id' => $site->id,
            'form_key' => $formKey,
            'data' => $data,
            'meta' => $meta,
        ]);

        if ($notifyEmail && filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::raw($this->formatEmail($formKey, $data), function ($message) use ($notifyEmail, $site, $formKey) {
                    $message->to($notifyEmail)->subject("[{$site->name}] New '{$formKey}' form submission");
                });
            } catch (\Throwable $e) {
                Log::warning('Form notification email failed (submission stored)', [
                    'site_id' => $site->id, 'form_key' => $formKey, 'error' => $e->getMessage(),
                ]);
            }
        }

        app(\App\Domain\Webhooks\WebhookDispatcher::class)->dispatch($site, 'form.submitted', [
            'form_key' => $formKey,
            'submission_id' => $submission->id,
            'values' => $data,
        ]);

        return ['stored' => true, 'submission' => $submission];
    }

    /**
     * Field name used in rendered form markup for a block field label —
     * single definition shared by the Blades (via this service) and the
     * validator so markup and validation can never drift apart.
     */
    public static function fieldName(string $label): string
    {
        return Str::slug($label, '_') ?: 'field';
    }

    /**
     * Normalize a form block's `fields` config into the validation schema.
     *
     * @return array<int, array{name: string, label: string, type: string, required: bool, options: array}>
     */
    public static function schemaFromBlock(array $blockFields): array
    {
        $schema = [];
        foreach ($blockFields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $schema[] = [
                'name' => self::fieldName($label),
                'label' => $label,
                'type' => in_array($field['type'] ?? 'text', ['text', 'email', 'textarea', 'select', 'checkbox', 'radio'], true)
                    ? $field['type'] : 'text',
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_values(array_filter(array_map('strval', (array) ($field['options'] ?? [])))),
            ];
        }

        return $schema;
    }

    private function tooFast(array $input): bool
    {
        $t = $input['_t'] ?? null;
        if (!is_numeric($t)) {
            return false; // no-JS visitors never set it
        }

        return (now()->getTimestampMs() - (int) $t) < self::MIN_FILL_MS;
    }

    /** @return array<string, mixed> validated label => value pairs */
    private function validate(array $schemaFields, array $input): array
    {
        $rules = [];
        $labels = [];
        foreach ($schemaFields as $field) {
            $name = $field['name'];
            $labels[$name] = $field['label'];
            $rule = [$field['required'] ? 'required' : 'nullable'];
            $rule[] = match ($field['type']) {
                'email' => 'email',
                'checkbox' => 'in:on,1,true',
                default => 'string',
            };
            if ($field['type'] !== 'checkbox') {
                $rule[] = 'max:5000';
            }
            if (in_array($field['type'], ['select', 'radio'], true) && $field['options'] !== []) {
                $rule[] = 'in:' . implode(',', $field['options']);
            }
            $rules[$name] = $rule;
        }

        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Store by human label, only schema fields (unknown keys dropped).
        $data = [];
        foreach ($schemaFields as $field) {
            $value = $input[$field['name']] ?? null;
            if ($field['type'] === 'checkbox') {
                $value = !empty($value);
            }
            $data[$field['label']] = is_string($value) ? mb_substr($value, 0, 5000) : $value;
        }

        return $data;
    }

    private function formatEmail(string $formKey, array $data): string
    {
        $lines = ["New '{$formKey}' form submission:\n"];
        foreach ($data as $label => $value) {
            $lines[] = "{$label}: " . (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
        }

        return implode("\n", $lines);
    }
}
