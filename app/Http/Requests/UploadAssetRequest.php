<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadAssetRequest extends FormRequest
{
    private array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx',
    ];

    private array $mimeToExtension = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
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

                // Check extension allowlist
                if (!in_array($extension, $this->allowedExtensions)) {
                    $validator->errors()->add('file', "File type .{$extension} is not allowed.");
                    return;
                }

                // Check MIME matches extension (images are cross-accepted)
                $mime = $file->getMimeType();
                $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $isImageExt = in_array($extension, $imageExts);
                $isImageMime = str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';

                if ($isImageExt && $isImageMime) {
                    // Any real image MIME is OK with any image extension
                } elseif ($extension === 'svg' && $mime === 'image/svg+xml') {
                    // SVG matches
                } else {
                    $allowedExts = $this->mimeToExtension[$mime] ?? null;
                    if (!$allowedExts || !in_array($extension, $allowedExts)) {
                        $validator->errors()->add('file', 'File MIME type does not match extension.');
                        return;
                    }
                }

                // Validate real images
                if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
                    if (!@getimagesize($file->getRealPath())) {
                        $validator->errors()->add('file', 'File is not a valid image.');
                        return;
                    }
                }

                // SVG security scan
                if ($mime === 'image/svg+xml') {
                    $content = file_get_contents($file->getRealPath());
                    if (preg_match('/<script|<foreignObject|on\w+\s*=/i', $content)) {
                        $validator->errors()->add('file', 'SVG contains potentially dangerous content.');
                        return;
                    }
                }
            },
        ];
    }
}
