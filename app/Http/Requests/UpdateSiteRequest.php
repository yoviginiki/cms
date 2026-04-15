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
            'settings' => ['sometimes', 'array'],
            'settings.head_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'settings.body_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'settings.custom_css' => ['sometimes', 'nullable', 'string', 'max:65536'],
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
