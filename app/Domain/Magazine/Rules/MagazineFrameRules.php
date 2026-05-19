<?php

namespace App\Domain\Magazine\Rules;

use App\Domain\Magazine\Enums\FrameType;

/**
 * Validation rules for editable frame attributes only.
 * Relational IDs (issue_id, spread_id, page_id, layer_id) are
 * controller/service-owned and validated separately with exists: checks.
 */
class MagazineFrameRules
{
    public static function rules(): array
    {
        return [
            'frame_type' => ['required', 'string', 'in:' . implode(',', array_column(FrameType::cases(), 'value'))],
            'name' => ['nullable', 'string', 'max:255'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'width' => ['required', 'numeric', 'min:1'],
            'height' => ['required', 'numeric', 'min:1'],
            'rotation' => ['numeric', 'min:0', 'max:360'],
            'z_index' => ['integer', 'min:-100', 'max:9999'],
            'visible' => ['boolean'],
            'locked' => ['boolean'],
            'content' => ['nullable', 'array'],
            'style' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public static function allowedFrameTypes(): array
    {
        return array_column(FrameType::cases(), 'value');
    }
}
