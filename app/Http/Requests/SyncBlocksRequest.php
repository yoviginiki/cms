<?php

namespace App\Http\Requests;

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
            'blocks.*.data' => ['present', 'array'],
            'blocks.*.order' => ['required', 'integer', 'min:0'],
            'blocks.*.id' => ['sometimes', 'uuid'],
            'blocks.*.style' => ['sometimes', 'nullable', 'array'],
            'blocks.*.animation' => ['sometimes', 'nullable', 'array'],
            'blocks.*.responsive' => ['sometimes', 'nullable', 'array'],
            'blocks.*.advanced' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children' => ['sometimes', 'array'],
            'blocks.*.children.*.type' => ['required_with:blocks.*.children', 'string'],
            'blocks.*.children.*.data' => ['present', 'array'],
            'blocks.*.children.*.order' => ['required_with:blocks.*.children', 'integer', 'min:0'],
            'blocks.*.children.*.id' => ['sometimes', 'uuid'],
            'blocks.*.children.*.style' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.animation' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.responsive' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.advanced' => ['sometimes', 'nullable', 'array'],
            'blocks.*.children.*.children' => ['sometimes', 'array'],
            'blocks.*.children.*.children.*.type' => ['required_with:blocks.*.children.*.children', 'string'],
            'blocks.*.children.*.children.*.data' => ['present', 'array'],
            'blocks.*.children.*.children.*.order' => ['required_with:blocks.*.children.*.children', 'integer', 'min:0'],
            'blocks.*.children.*.children.*.id' => ['sometimes', 'uuid'],
            'blocks.*.children.*.children.*.style' => ['sometimes', 'nullable', 'array'],
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
                if ($maxDepth > 3) {
                    $validator->errors()->add('blocks', 'Maximum nesting depth is 3 levels.');
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
