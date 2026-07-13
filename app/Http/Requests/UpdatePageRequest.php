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
            'editor_mode' => ['sometimes', 'in:block,magazine,canvas'],
            'experience_mode' => ['sometimes', 'in:standard,cinematic'],
            'layout_id' => ['sometimes', 'nullable', 'uuid'],
            'seo_meta' => ['sometimes', 'array'],
            'seo_meta.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_meta.description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_meta.og_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo_meta.canonical' => ['sometimes', 'nullable', 'url', 'max:500'],
            'seo_meta.no_index' => ['sometimes', 'boolean'],
            'seo_meta.no_follow' => ['sometimes', 'boolean'],
            'seo_meta.head_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'seo_meta.body_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'seo_meta.custom_css' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'seo_meta.pageStyle' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageStyle.spacing' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageStyle.visual' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageStyle.layout' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageStyle.typography' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageData' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageAnimation' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageResponsive' => ['sometimes', 'nullable', 'array'],
            'seo_meta.pageAdvanced' => ['sometimes', 'nullable', 'array'],
            // Cinematic atmosphere toggles
            'seo_meta.experience_preloader' => ['sometimes', 'boolean'],
            'seo_meta.experience_cursor' => ['sometimes', 'boolean'],
            'seo_meta.experience_sound' => ['sometimes', 'boolean'],
            'seo_meta.experience_sound_asset' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // _previous_paths is server-managed (delta stale-file cleanup) —
        // never accepted from clients regardless of role.
        $seoMeta = $this->input('seo_meta');
        if (is_array($seoMeta) && array_key_exists('_previous_paths', $seoMeta)) {
            unset($seoMeta['_previous_paths']);
            $this->merge(['seo_meta' => $seoMeta]);
        }

        if ($this->user() && !$this->user()->hasMinimumRole('admin')) {
            $seoMeta = $this->input('seo_meta', []);
            unset($seoMeta['head_scripts'], $seoMeta['body_scripts'], $seoMeta['custom_css']);
            $this->merge(['seo_meta' => $seoMeta]);
        }
    }
}
