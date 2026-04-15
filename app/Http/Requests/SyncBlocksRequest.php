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
            'blocks.*.data' => ['required', 'array'],
            'blocks.*.order' => ['required', 'integer', 'min:0'],
            'blocks.*.id' => ['sometimes', 'uuid'],
            'blocks.*.children' => ['sometimes', 'array'],
            'blocks.*.children.*.type' => ['required_with:blocks.*.children', 'string'],
            'blocks.*.children.*.data' => ['required_with:blocks.*.children', 'array'],
            'blocks.*.children.*.order' => ['required_with:blocks.*.children', 'integer', 'min:0'],
            'blocks.*.children.*.id' => ['sometimes', 'uuid'],
            'blocks.*.children.*.children' => ['sometimes', 'array'],
            'blocks.*.children.*.children.*.type' => ['required_with:blocks.*.children.*.children', 'string'],
            'blocks.*.children.*.children.*.data' => ['required_with:blocks.*.children.*.children', 'array'],
            'blocks.*.children.*.children.*.order' => ['required_with:blocks.*.children.*.children', 'integer', 'min:0'],
            'blocks.*.children.*.children.*.id' => ['sometimes', 'uuid'],
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
