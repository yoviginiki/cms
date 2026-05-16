<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadAssetRequest extends FormRequest
{
    public const DEFAULT_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'txt', 'md',
        'mp3', 'mp4', 'mov', 'mpg',
        'zip', 'rar',
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
        'text/plain' => ['txt', 'md'],
        'text/markdown' => ['md'],
        'audio/mpeg' => ['mp3'],
        'video/mp4' => ['mp4'],
        'video/quicktime' => ['mov'],
        'video/mpeg' => ['mpg'],
        'application/zip' => ['zip'],
        'application/x-rar-compressed' => ['rar'],
    ];

    /** Extensions that are NEVER allowed regardless of site settings */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'sh', 'bash', 'cgi', 'pl', 'py', 'rb',
        'exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'scr',
        'htaccess', 'htpasswd', 'env',
        'jsp', 'asp', 'aspx',
    ];

    private function getAllowedExtensions(): array
    {
        $site = $this->route('site');
        $extensions = (!empty($site?->settings['allowed_extensions']))
            ? $site->settings['allowed_extensions']
            : self::DEFAULT_EXTENSIONS;

        // Always remove dangerous extensions
        return array_values(array_diff($extensions, self::BLOCKED_EXTENSIONS));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:102400'], // 100MB max
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

                // Check extension allowlist (from site settings or defaults)
                $allowedExtensions = $this->getAllowedExtensions();
                if (!in_array($extension, $allowedExtensions)) {
                    $validator->errors()->add('file', "File type .{$extension} is not allowed.");
                    return;
                }

                // Check MIME matches extension (strict for images, permissive for others)
                $mime = $file->getMimeType();
                $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $isImageExt = in_array($extension, $imageExts);
                $isImageMime = str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';

                if ($isImageExt && $isImageMime) {
                    // Any real image MIME is OK with any image extension
                } elseif ($extension === 'svg' && $mime === 'image/svg+xml') {
                    // SVG matches
                } elseif (str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/') ||
                          str_starts_with($mime, 'text/') || $mime === 'application/octet-stream' ||
                          str_starts_with($mime, 'application/zip') || str_starts_with($mime, 'application/x-rar')) {
                    // Permissive for media, text, and archives — extension check is sufficient
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
