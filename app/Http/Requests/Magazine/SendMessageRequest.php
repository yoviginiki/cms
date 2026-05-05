<?php

namespace App\Http\Requests\Magazine;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'step' => ['required', 'integer', 'min:1', 'max:7'],
            'content' => ['required', 'string', 'max:10000'],
        ];
    }
}
