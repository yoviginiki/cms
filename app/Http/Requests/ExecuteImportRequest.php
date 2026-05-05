<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasMinimumRole('admin');
    }

    public function rules(): array
    {
        return [
            'import_categories' => ['sometimes', 'boolean'],
            'import_pages' => ['sometimes', 'boolean'],
            'import_posts' => ['sometimes', 'boolean'],
            'import_media' => ['sometimes', 'boolean'],
        ];
    }
}
