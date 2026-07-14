<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\FieldTypes;
use App\Domain\Publishing\Services\SanitizationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns raw record input into clean, typed, sanitized `data` for one
 * collection schema — every value passes through here before it may touch the
 * database, mirroring how block data passes SanitizationService. Collects all
 * field errors into one ValidationException (keys: data.{field_key}).
 *
 * Relation fields are NOT processed here (they live in record_relations, see
 * RecordService); their pivot values reuse processFields() with pivot_fields.
 */
class RecordDataProcessor
{
    public function __construct(private SanitizationService $sanitizer)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $fields validated schema field defs
     * @param array<string, mixed> $input raw values keyed by field key
     * @param string $errorPrefix ValidationException key prefix
     * @return array<string, mixed> clean data (empty optional fields omitted)
     */
    public function processFields(array $fields, array $input, string $errorPrefix = 'data'): array
    {
        $clean = [];
        $errors = [];

        foreach ($fields as $field) {
            if ($field['type'] === 'relation') {
                continue;
            }

            $key = $field['key'];
            $raw = $input[$key] ?? null;

            try {
                $value = $this->processValue($field, $raw);
                if ($value === null) {
                    if ($field['required'] && $field['type'] !== 'boolean') {
                        $errors["{$errorPrefix}.{$key}"] = "{$field['label']} is required.";
                    }
                } else {
                    $clean[$key] = $value;
                }
            } catch (FieldValueException $e) {
                $errors["{$errorPrefix}.{$key}"] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * @return mixed the clean value, or null when empty/absent
     * @throws FieldValueException when the raw value is invalid for the type
     */
    private function processValue(array $field, mixed $raw): mixed
    {
        if ($raw === null || $raw === '' || ($raw === [] && in_array($field['type'], ['multi_select', 'gallery'], true))) {
            return $field['type'] === 'boolean' ? $this->toBool($raw ?? false) : null;
        }

        $settings = $field['settings'] ?? [];
        $label = $field['label'];

        return match ($field['type']) {
            'text' => $this->text($raw, $label, (int) ($settings['max_length'] ?? 500)),
            'rich_text' => $this->richText($raw, $label),
            'number' => $this->number($raw, $label, $settings),
            'price' => $this->price($raw, $label),
            'boolean' => $this->toBool($raw),
            'select' => $this->select($raw, $label, $field['options'] ?? []),
            'multi_select' => $this->multiSelect($raw, $label, $field['options'] ?? []),
            'date' => $this->date($raw, $label),
            'email' => $this->email($raw, $label),
            'url' => $this->url($raw, $label),
            'phone' => $this->phone($raw, $label),
            'image', 'file' => $this->assetId($raw, $label),
            'gallery' => $this->gallery($raw, $label),
            'sku' => $this->sku($raw, $label),
            default => throw new FieldValueException("Unsupported field type."),
        };
    }

    private function text(mixed $raw, string $label, int $maxLength): string
    {
        if (!is_scalar($raw)) {
            throw new FieldValueException("{$label} must be text.");
        }
        $value = trim(strip_tags((string) $raw));
        if ($value === '') {
            throw new FieldValueException("{$label} must be text.");
        }
        if (mb_strlen($value) > $maxLength) {
            throw new FieldValueException("{$label} is too long (max {$maxLength} characters).");
        }

        return $value;
    }

    private function richText(mixed $raw, string $label): string
    {
        if (!is_string($raw)) {
            throw new FieldValueException("{$label} must be text.");
        }
        if (mb_strlen($raw) > 200000) {
            throw new FieldValueException("{$label} is too long.");
        }

        return $this->sanitizer->purifyRich($raw);
    }

    private function number(mixed $raw, string $label, array $settings): int|float
    {
        if (!is_numeric($raw)) {
            throw new FieldValueException("{$label} must be a number.");
        }
        $value = $raw + 0;
        if (isset($settings['min']) && is_numeric($settings['min']) && $value < $settings['min']) {
            throw new FieldValueException("{$label} must be at least {$settings['min']}.");
        }
        if (isset($settings['max']) && is_numeric($settings['max']) && $value > $settings['max']) {
            throw new FieldValueException("{$label} must be at most {$settings['max']}.");
        }

        return $value;
    }

    private function price(mixed $raw, string $label): float
    {
        if (is_string($raw)) {
            $raw = str_replace([',', ' '], ['.', ''], trim($raw));
        }
        if (!is_numeric($raw)) {
            throw new FieldValueException("{$label} must be a price.");
        }
        $value = round((float) $raw, 2);
        if ($value < 0) {
            throw new FieldValueException("{$label} can't be negative.");
        }

        return $value;
    }

    private function toBool(mixed $raw): bool
    {
        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    private function select(mixed $raw, string $label, array $options): string
    {
        if (!is_string($raw) || !in_array($raw, $options, true)) {
            throw new FieldValueException("{$label}: pick one of the defined options.");
        }

        return $raw;
    }

    private function multiSelect(mixed $raw, string $label, array $options): array
    {
        if (!is_array($raw)) {
            throw new FieldValueException("{$label}: pick from the defined options.");
        }
        $values = [];
        foreach ($raw as $v) {
            if (!is_string($v) || !in_array($v, $options, true)) {
                throw new FieldValueException("{$label}: pick from the defined options.");
            }
            if (!in_array($v, $values, true)) {
                $values[] = $v;
            }
        }

        return $values;
    }

    private function date(mixed $raw, string $label): string
    {
        if (!is_string($raw)) {
            throw new FieldValueException("{$label} must be a date.");
        }
        try {
            return Carbon::parse(trim($raw))->format('Y-m-d');
        } catch (\Throwable) {
            throw new FieldValueException("{$label} must be a valid date.");
        }
    }

    private function email(mixed $raw, string $label): string
    {
        $value = is_string($raw) ? mb_strtolower(trim($raw)) : '';
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new FieldValueException("{$label} must be a valid email address.");
        }

        return $value;
    }

    private function url(mixed $raw, string $label): string
    {
        $value = is_string($raw) ? trim($raw) : '';
        $scheme = mb_strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new FieldValueException("{$label} must be an http(s) URL.");
        }
        if (mb_strlen($value) > 2000) {
            throw new FieldValueException("{$label} is too long.");
        }

        return $value;
    }

    private function phone(mixed $raw, string $label): string
    {
        $value = is_scalar($raw) ? trim((string) $raw) : '';
        if ($value === '' || mb_strlen($value) > 40 || !preg_match('/^[+0-9()\-.\/ ]+$/', $value)) {
            throw new FieldValueException("{$label} must be a phone number.");
        }

        return $value;
    }

    private function assetId(mixed $raw, string $label): string
    {
        if (!is_string($raw) || !Str::isUuid($raw)) {
            throw new FieldValueException("{$label}: invalid asset reference.");
        }

        return $raw;
    }

    private function gallery(mixed $raw, string $label): array
    {
        if (!is_array($raw) || count($raw) > 100) {
            throw new FieldValueException("{$label}: invalid gallery (max 100 images).");
        }
        $ids = [];
        foreach ($raw as $id) {
            if (!is_string($id) || !Str::isUuid($id)) {
                throw new FieldValueException("{$label}: invalid asset reference.");
            }
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function sku(mixed $raw, string $label): string
    {
        if (!is_scalar($raw)) {
            throw new FieldValueException("{$label} must be a part number.");
        }
        $value = FieldTypes::normalizeSku(strip_tags((string) $raw));
        if ($value === '' || mb_strlen($value) > 120) {
            throw new FieldValueException("{$label} must be a part number (max 120 characters).");
        }

        return $value;
    }
}
