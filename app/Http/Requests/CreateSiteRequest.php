<?php

namespace App\Http\Requests;

use App\Domain\Publishing\Services\DeployTargetResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
                // One reserved-name policy for create/update/deploy (F03).
                $targets = app(DeployTargetResolver::class);
                // H01: SSH deploy fields are validated as a unit whenever the
                // resulting settings use the ssh method (create has no per-key rules).
                $existing = $this->route('site') instanceof \App\Models\Site ? ($this->route('site')->settings ?? []) : [];
                $merged = array_merge($existing, \App\Domain\Sites\Support\SiteSecrets::mergeIncoming($existing, (array) $this->input('settings', [])));
                if (($merged['deploy_method'] ?? 'local') === 'ssh') {
                    foreach (\App\Domain\Publishing\Services\Deploy\SshTarget::errors($merged) as $key => $msg) {
                        $validator->errors()->add("settings.{$key}", "The {$key} {$msg}");
                    }
                }
                $slug = $this->input('slug');
                $domain = $this->input('custom_domain');

                if ($slug && $targets->isReservedSlug($slug)) {
                    $validator->errors()->add('slug', 'This slug is reserved and cannot be used.');
                }

                if ($domain && $targets->isReservedDomain($domain)) {
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
