<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMinimumRole('admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:sites,slug'],
            'custom_domain' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:sites,custom_domain', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i'],
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
