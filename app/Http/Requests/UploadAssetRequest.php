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

    /** Cheap content sniff for text uploads: HTML/XML/SVG/script openers. */
    public static function looksLikeMarkup(string $head): bool
    {
        $head = ltrim($head, "\xEF\xBB\xBF \t\r\n");

        return (bool) preg_match('/^(<!doctype\s+html|<html|<\?xml|<svg|<script|<iframe|<object|<embed)\b/i', $head)
            || (bool) preg_match('/<script\b|<iframe\b|<object\b|<embed\b|javascript:/i', $head);
    }

    /** MIME types that a browser would execute or parse as active markup. */
    public static function isActiveContentMime(string $mime): bool
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));

        return in_array($mime, [
            'text/html', 'application/xhtml+xml', 'text/xml', 'application/xml',
            'text/javascript', 'application/javascript', 'application/x-javascript', 'application/ecmascript',
            'text/vbscript', 'application/x-shockwave-flash', 'text/x-php', 'application/x-httpd-php',
        ], true) || str_ends_with($mime, '+xml') && $mime !== 'image/svg+xml';
    }

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
            'folder' => ['sometimes', 'nullable', 'string', 'max:255'],
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
                } elseif (self::isActiveContentMime($mime)) {
                    // F06: HTML/XML/JS under an allowed extension (.txt/.md) would be
                    // stored with the sniffed MIME and could be served from the CMS
                    // origin — refused regardless of extension.
                    $validator->errors()->add('file', 'Active content (HTML, XML, scripts) cannot be uploaded as a text file.');
                    return;
                } elseif (in_array($extension, ['txt', 'md'], true)) {
                    // Text extensions: only genuine plain text / markdown.
                    if (!in_array($mime, ['text/plain', 'text/markdown', 'text/x-markdown'], true)) {
                        $validator->errors()->add('file', 'File MIME type does not match extension.');
                        return;
                    }
                    if (self::looksLikeMarkup((string) @file_get_contents($file->getRealPath(), false, null, 0, 4096))) {
                        $validator->errors()->add('file', 'Active content (HTML, XML, scripts) cannot be uploaded as a text file.');
                        return;
                    }
                } elseif (str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/') ||
                          $mime === 'application/octet-stream' ||
                          str_starts_with($mime, 'application/zip') || str_starts_with($mime, 'application/x-rar')) {
                    // Permissive for media and archives — extension check is sufficient
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
