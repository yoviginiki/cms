<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.parent_id' => ['sometimes', 'nullable', 'uuid'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
