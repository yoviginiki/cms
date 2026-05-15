<?php

namespace App\Http\Requests;

use App\Support\Blocks\HierarchyValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncBlocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'blocks' => ['required', 'array'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.level' => ['sometimes', 'in:section,row,column,module'],
            'blocks.*.data' => ['present', 'array'],
            'blocks.*.order' => ['required', 'integer', 'min:0'],
            'blocks.*.id' => ['sometimes', 'uuid'],
            'blocks.*.preset_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'blocks.*.style' => ['sometimes', 'nullable', 'array'],
            'blocks.*.animation' => ['sometimes', 'nullable', 'array'],
            'blocks.*.responsive' => ['sometimes', 'nullable', 'array'],
            'blocks.*.advanced' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children' => ['sometimes', 'array'],
            // Level 2: children (rows)
            'blocks.*.children.*.type' => ['required_with:blocks.*.children', 'string'],
            'blocks.*.children.*.level' => ['sometimes', 'in:section,row,column,module'],
            'blocks.*.children.*.data' => ['present', 'array'],
            'blocks.*.children.*.order' => ['required_with:blocks.*.children', 'integer', 'min:0'],
            'blocks.*.children.*.id' => ['sometimes', 'uuid'],
            'blocks.*.children.*.preset_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'blocks.*.children.*.style' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.animation' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.responsive' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.advanced' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.children' => ['sometimes', 'array'],
            // Level 3: grandchildren (columns)
            'blocks.*.children.*.children.*.type' => ['required_with:blocks.*.children.*.children', 'string'],
            'blocks.*.children.*.children.*.level' => ['sometimes', 'in:section,row,column,module'],
            'blocks.*.children.*.children.*.data' => ['present', 'array'],
            'blocks.*.children.*.children.*.order' => ['required_with:blocks.*.children.*.children', 'integer', 'min:0'],
            'blocks.*.children.*.children.*.id' => ['sometimes', 'uuid'],
            'blocks.*.children.*.children.*.preset_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'blocks.*.children.*.children.*.style' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.children.*.animation' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.children.*.responsive' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.children.*.advanced' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.children.*.children' => ['sometimes', 'array'],
            // Level 4: modules (leaf nodes)
            'blocks.*.children.*.children.*.children.*.type' => ['required_with:blocks.*.children.*.children.*.children', 'string'],
            'blocks.*.children.*.children.*.children.*.level' => ['sometimes', 'in:section,row,column,module'],
            'blocks.*.children.*.children.*.children.*.data' => ['present', 'array'],
            'blocks.*.children.*.children.*.children.*.order' => ['required_with:blocks.*.children.*.children.*.children', 'integer', 'min:0'],
            'blocks.*.children.*.children.*.children.*.id' => ['sometimes', 'uuid'],
            'blocks.*.children.*.children.*.children.*.preset_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'blocks.*.children.*.children.*.children.*.style' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $blocks = $this->input('blocks', []);
                $totalCount = $this->countBlocks($blocks);

                if ($totalCount > 500) {
                    $validator->errors()->add('blocks', 'Maximum 500 blocks per page.');
                }

                $maxDepth = $this->maxDepth($blocks);
                if ($maxDepth > 4) {
                    $validator->errors()->add('blocks', 'Maximum nesting depth is 4 levels (Section → Row → Column → Module).');
                }

                // Validate hierarchy containment rules (only if any block at any depth has level field)
                $hasLevels = $this->anyBlockHasLevel($blocks);
                if ($hasLevels) {
                    $hierarchyResult = HierarchyValidator::validate($blocks);
                    if (!$hierarchyResult->valid) {
                        foreach ($hierarchyResult->errors as $error) {
                            $validator->errors()->add($error['path'], $error['message']);
                        }
                    }
                }
            },
        ];
    }

    private function countBlocks(array $blocks): int
    {
        $count = count($blocks);
        foreach ($blocks as $block) {
            if (!empty($block['children'])) {
                $count += $this->countBlocks($block['children']);
            }
        }
        return $count;
    }

    private function anyBlockHasLevel(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (!empty($block['level'])) {
                return true;
            }
            if (!empty($block['children']) && $this->anyBlockHasLevel($block['children'])) {
                return true;
            }
        }
        return false;
    }

    private function maxDepth(array $blocks, int $depth = 1): int
    {
        $max = $depth;
        foreach ($blocks as $block) {
            if (!empty($block['children'])) {
                $max = max($max, $this->maxDepth($block['children'], $depth + 1));
            }
        }
        return $max;
    }
}
