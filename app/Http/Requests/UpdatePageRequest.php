<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'uuid', 'exists:pages,id'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'editor_mode' => ['sometimes', 'in:block,magazine'],
            'layout_id' => ['sometimes', 'nullable', 'uuid'],
            'seo_meta' => ['sometimes', 'array'],
            'seo_meta.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_meta.description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_meta.og_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo_meta.head_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'seo_meta.body_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'seo_meta.custom_css' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'seo_meta.pageStyle' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageData' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageAnimation' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageResponsive' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageAdvanced' => ['sometimes', 'nullable', 'array'],
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
