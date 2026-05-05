<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasMinimumRole('admin');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:51200'], // 50MB max
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $file = $this->file('file');
                if (!$file) {
                    return;
                }

                $extension = strtolower($file->getClientOriginalExtension());
                if (!in_array($extension, ['xml', 'wxr'])) {
                    $validator->errors()->add('file', 'File must be an XML or WXR file.');
                    return;
                }

                // Quick XML validation
                $content = file_get_contents($file->getRealPath());
                if (!str_contains($content, '<rss') && !str_contains($content, 'xmlns:wp')) {
                    $validator->errors()->add('file', 'File does not appear to be a valid WordPress WXR export.');
                    return;
                }

                // Check for WXR namespace
                if (!str_contains($content, 'wordpress.org/export')) {
                    $validator->errors()->add('file', 'File is missing WordPress export namespace.');
                }
            },
        ];
    }
}
