<?php

namespace App\Http\Requests\Magazine;

use Illuminate\Foundation\Http\FormRequest;

class LockStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'step' => ['required', 'integer', 'min:1', 'max:7'],
            'locked_artifact' => ['required', 'array'],
        ];
    }
}
