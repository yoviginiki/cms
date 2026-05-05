<?php

namespace App\Domain\Blocks\Definitions;

class ContactFormBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'contact-form'; }
    public function category(): string { return 'forms'; }

    public function validationRules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.type' => ['required', 'in:text,email,tel,textarea,select,checkbox'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'recipient_email' => ['required', 'email'],
            'success_message' => ['sometimes', 'string', 'max:500'],
            'submit_label' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => '',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
