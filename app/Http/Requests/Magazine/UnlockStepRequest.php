<?php

namespace App\Http\Requests\Magazine;

use Illuminate\Foundation\Http\FormRequest;

class UnlockStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'to_step' => ['required', 'integer', 'min:1', 'max:7'],
        ];
    }
}
