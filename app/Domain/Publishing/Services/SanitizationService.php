<?php
namespace App\Domain\Publishing\Services;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Models\Block;
use HTMLPurifier;
use HTMLPurifier_Config;

class SanitizationService
{
    private HTMLPurifier $purifier;
    private HTMLPurifier $strictPurifier;

    public function __construct(private BlockRegistry $registry)
    {
        // Suppress HTMLPurifier's E_USER_WARNING for unsupported elements
        $previousHandler = set_error_handler(function ($severity, $message) use (&$previousHandler) {
            if ($severity === E_USER_WARNING && str_contains($message, 'is not supported')) {
                return true; // Suppress
            }
            if ($previousHandler) {
                return $previousHandler($severity, $message);
            }
            return false;
        });

        // Rich text purifier
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,u,a[href|target|rel],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,code,pre,table,thead,tbody,tr,th,td,img[src|alt|width|height],span[class],hr');
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('Cache.DefinitionImpl', null);
        $this->purifier = new HTMLPurifier($config);

        // Strict purifier (no HTML at all)
        $strictConfig = HTMLPurifier_Config::createDefault();
        $strictConfig->set('HTML.Allowed', '');
        $strictConfig->set('Cache.DefinitionImpl', null);
        $this->strictPurifier = new HTMLPurifier($strictConfig);

        // Restore original error handler
        restore_error_handler();
    }

    /**
     * Purify a rich-HTML string with the same profile blocks use. Public so
     * the magazine/DTP renderers share ONE sanitize path (S8 — they used bare
     * strip_tags, which let event handlers and javascript: URLs through).
     */
    public function purifyRich(string $html): string
    {
        return @$this->purifier->purify($html);
    }

    public function sanitizeBlock(Block $block): array
    {
        $data = $block->data ?? [];
        $definition = $this->registry->get($block->type);

        // Get per-block sanitization config
        $sanitizeConfig = $definition?->sanitizationConfig() ?? [];
        $allowedHtml = $sanitizeConfig['HTML.Allowed'] ?? '';

        // Check if block has allowHtml flag for safe inline HTML in text fields
        $blockAllowHtml = !empty($data['allowHtml']);
        // Fields that can contain safe inline HTML when allowHtml is set
        $inlineHtmlFields = ['text', 'heading', 'title', 'quote'];

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            // Fields that should allow rich HTML
            if ($key === 'content' && $allowedHtml !== '') {
                $sanitized[$key] = @$this->purifier->purify($value);
            } elseif ($blockAllowHtml && in_array($key, $inlineHtmlFields)) {
                // Allow safe inline HTML: <br>, <em>, <strong>, <span>
                $sanitized[$key] = strip_tags($value, '<br><em><strong><span>');
            } else {
                // Strip all HTML for plain text fields
                $sanitized[$key] = @$this->strictPurifier->purify($value);
            }
        }

        return $sanitized;
    }
}
