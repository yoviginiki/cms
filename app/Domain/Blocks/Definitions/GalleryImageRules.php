<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Shared validation for gallery-style image lists. Items are either legacy
 * bare URL strings or objects {id, src, alt, caption, link, width, height}
 * (what the admin's GalleryImagesField writes).
 */
final class GalleryImageRules
{
    public static function rules(string $field = 'images'): array
    {
        $noScript = 'not_regex:/^(javascript|data|vbscript):/i';

        return [
            $field                => ['sometimes', 'array', 'max:500'],
            "{$field}.*"          => ['sometimes', 'nullable', function (string $attr, mixed $value, \Closure $fail) {
                if (is_string($value)) {
                    if (strlen($value) > 2048) {
                        $fail("{$attr} is too long.");
                    } elseif (preg_match('/^(javascript|data|vbscript):/i', $value)) {
                        $fail("{$attr} has an unsupported URL scheme.");
                    }
                } elseif (!is_array($value)) {
                    $fail("{$attr} must be an image URL or an image object.");
                }
            }],
            "{$field}.*.id"       => ['sometimes', 'nullable', 'string', 'max:64'],
            "{$field}.*.src"      => ['sometimes', 'nullable', 'string', 'max:2048', $noScript],
            "{$field}.*.url"      => ['sometimes', 'nullable', 'string', 'max:2048', $noScript],
            "{$field}.*.alt"      => ['sometimes', 'nullable', 'string', 'max:500'],
            "{$field}.*.caption"  => ['sometimes', 'nullable', 'string', 'max:1000'],
            "{$field}.*.link"     => ['sometimes', 'nullable', 'string', 'max:2048', $noScript],
            "{$field}.*.width"    => ['sometimes', 'nullable', 'integer', 'min:0'],
            "{$field}.*.height"   => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
