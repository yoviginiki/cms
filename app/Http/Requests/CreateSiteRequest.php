<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateSiteRequest extends FormRequest
{
    private array $reservedDomains = ['sys.ensodo.eu', 'admin.ensodo.eu', 'api.ensodo.eu'];
    private array $reservedSlugs = ['sys', 'admin', 'api', 'login', 'register'];

    public function authorize(): bool
    {
        return $this->user()->hasMinimumRole('admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:sites,slug'],
            'custom_domain' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:sites,custom_domain', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i'],
            'seo_defaults' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
            'settings.head_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'settings.body_scripts' => ['sometimes', 'nullable', 'string', 'max:65536'],
            'settings.custom_css' => ['sometimes', 'nullable', 'string', 'max:65536'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $slug = $this->input('slug');
                $domain = $this->input('custom_domain');

                if ($slug && in_array(strtolower($slug), $this->reservedSlugs)) {
                    $validator->errors()->add('slug', 'This slug is reserved and cannot be used.');
                }

                if ($domain && in_array(strtolower($domain), $this->reservedDomains)) {
                    $validator->errors()->add('custom_domain', 'This domain is reserved for the admin panel.');
                }
            },
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
