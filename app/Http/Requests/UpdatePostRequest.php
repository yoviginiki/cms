<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
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
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'featured_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'video_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'thumbnail' => ['sometimes', 'nullable', 'string', 'max:500'],
            'post_format' => ['sometimes', 'in:standard,video,gallery,audio,link'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'editor_mode' => ['sometimes', 'in:simple,block,magazine'],
            'layout_id' => ['sometimes', 'nullable', 'uuid'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
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
