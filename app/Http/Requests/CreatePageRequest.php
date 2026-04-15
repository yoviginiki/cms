<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->route('site')?->id ?? $this->route('site');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'uuid', "exists:pages,id"],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'seo_meta' => ['sometimes', 'array'],
            'seo_meta.head_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'seo_meta.body_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'seo_meta.custom_css' => ['sometimes', 'nullable', 'string', 'max:65536'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->user() && !$this->user()->hasMinimumRole('admin')) {
            $seoMeta = $this->input('seo_meta', []);
            unset($seoMeta['head_scripts'], $seoMeta['body_scripts'], $seoMeta['custom_css']);
            $this->merge(['seo_meta' => $seoMeta]);
        }
    }
}
