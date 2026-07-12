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
            'seo_defaults.feed_full_content' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.auto_publish' => ['sometimes', 'boolean'],
            'settings.llms_txt' => ['sometimes', 'boolean'],
            'settings.ai_crawlers_disallowed' => ['sometimes', 'array'],
            'settings.ai_crawlers_disallowed.*' => ['sometimes', 'string', 'max:50'],
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
            'settings.deploy_method' => ['sometimes', 'in:local,ssh,zip_only'],
            'settings.deploy_ssh_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings.deploy_ssh_user' => ['sometimes', 'nullable', 'string', 'max:100'],
            'settings.deploy_ssh_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'settings.deploy_ssh_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'settings.deploy_ssh_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Global styles
            'settings.global_font_family' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings.global_font_size' => ['sometimes', 'nullable', 'string', 'max:20'],
            'settings.global_line_height' => ['sometimes', 'nullable', 'string', 'max:20'],
            'settings.global_text_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'settings.global_bg_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'settings.global_link_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'settings.global_container_width' => ['sometimes', 'nullable', 'string', 'max:20'],
            'settings.global_container_padding' => ['sometimes', 'nullable', 'string', 'max:20'],
            // Custom cursor
            'settings.cursor_enabled' => ['sometimes', 'boolean'],
            'settings.cursor_preset' => ['sometimes', 'string', 'in:dot-ring,circle-dot,minimal,crosshair,ring-only,glow,spotlight,dash-ring,square,arrow-dot'],
            'settings.cursor_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'settings.cursor_ring_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'settings.cursor_blend' => ['sometimes', 'string', 'in:normal,difference,exclusion'],
            'settings.cursor_size' => ['sometimes', 'string', 'in:sm,md,lg'],
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
