<?php

namespace App\Http\Requests\Magazine;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
