<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMinimumRole('admin');
    }

    public function rules(): array
    {
        $siteId = $this->route('site')?->id ?? $this->route('site');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'custom_domain' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('sites', 'custom_domain')->ignore($siteId), 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i'],
            'status' => ['sometimes', 'in:active,paused,archived'],
            'seo_defaults' => ['sometimes', 'array'],
            'seo_defaults.title_template' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_defaults.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo_defaults.og_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'settings' => ['sometimes', 'array'],
            'settings.auto_publish' => ['sometimes', 'boolean'],
            'settings.homepage_type' => ['sometimes', 'in:page,grid,blog'],
            'settings.homepage_id' => ['sometimes', 'nullable', 'uuid'],
            'settings.homepage_grid_id' => ['sometimes', 'nullable', 'uuid'],
            'settings.blog_page_id' => ['sometimes', 'nullable', 'uuid'],
            'settings.head_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'settings.body_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'settings.custom_css' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'settings.anthropic_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings.openai_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings.mag_transition' => ['sometimes', 'string', 'max:50'],
            'settings.mag_spread' => ['sometimes', 'string', 'max:50'],
            'settings.mag_bg' => ['sometimes', 'string', 'max:20'],
            'settings.mag_speed' => ['sometimes', 'integer', 'min:100', 'max:3000'],
            'settings.mag_page_numbers' => ['sometimes', 'boolean'],
            'settings.mag_pn_position' => ['sometimes', 'string', 'max:20'],
            'settings.mag_pn_align' => ['sometimes', 'string', 'max:20'],
            'settings.mag_pn_size' => ['sometimes', 'string', 'max:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->user() && !$this->user()->hasMinimumRole('admin')) {
            $settings = $this->input('settings', []);
            unset($settings['head_scripts'], $settings['body_scripts'], $settings['custom_css']);
            $this->merge(['settings' => $settings]);
        }
    }
}
