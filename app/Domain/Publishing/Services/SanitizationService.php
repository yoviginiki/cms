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
    private HTMLPurifier $magazinePurifier;
    private HTMLPurifier $inlinePurifier;

    /** Nested string keys that may carry safe rich HTML (rendered via {!! !!}). */
    private const HTML_LEAF_KEYS = ['content', 'contentSecondary', 'html', 'body', 'text', 'caption', 'description', 'answer'];

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

        // Inline purifier — a small set of formatting tags with NO attributes.
        // Replaces the old strip_tags() path, which left event-handler
        // attributes (onclick/onmouseover/...) intact on allowed tags.
        $inlineConfig = HTMLPurifier_Config::createDefault();
        $inlineConfig->set('HTML.Allowed', 'br,em,strong,b,i,span,sub,sup');
        $inlineConfig->set('Cache.DefinitionImpl', null);
        $this->inlinePurifier = new HTMLPurifier($inlineConfig);

        // Magazine/DTP purifier — mirrors the editor's DOMPurify allowlist
        // (MagElementRenderer SAFE_HTML_CONFIG) so published slices render the
        // markup the editor produced: b/i from execCommand formatting, inline
        // images with float/width styles, and the flow engine's continued-
        // fragment margin resets (style="margin-top:0;text-indent:0").
        // CSS is constrained here AND re-filtered by DtpRenderService's own
        // property allowlist (defense in depth).
        $magConfig = HTMLPurifier_Config::createDefault();
        $magConfig->set('HTML.Allowed',
            'p[style],br,b,i,u,em,strong,s,span[class|style],a[href|target|rel],' .
            'ul[style],ol[style],li,h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],' .
            'blockquote[style],sub,sup,hr,div[style],figure[style],figcaption[style],' .
            'img[src|alt|width|height|style]');
        $magConfig->set('CSS.AllowedProperties', [
            'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right',
            'padding', 'text-indent', 'text-align', 'float', 'width', 'height',
            'max-width', 'border-radius', 'border', 'display',
            'font-size', 'opacity', 'column-span',
        ]);

        $magConfig->set('HTML.TargetBlank', true);
        $magConfig->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $magConfig->set('Attr.AllowedFrameTargets', ['_blank']);
        $magConfig->set('AutoFormat.RemoveEmpty', false);
        $magConfig->set('Cache.DefinitionImpl', null);
        // HTMLPurifier predates HTML5 — it silently DROPS figure/figcaption
        // (inline images in text frames lost their wrapper + float/width styles
        // at publish). Teach the raw definition both elements, and teach the
        // CSS definition column-span (figures spanning all text columns).
        // Definition getters finalize the config, so these stay the last steps.
        $magConfig->set('HTML.DefinitionID', 'stillopress-magazine');
        $magConfig->set('HTML.DefinitionRev', 1);
        if ($htmlDef = $magConfig->maybeGetRawHTMLDefinition()) {
            $htmlDef->addElement('figure', 'Block', 'Flow', 'Common');
            $htmlDef->addElement('figcaption', 'Block', 'Flow', 'Common');
        }
        $magConfig->getCSSDefinition()->info['column-span'] = new \HTMLPurifier_AttrDef_Enum(['none', 'all']);
        $this->magazinePurifier = new HTMLPurifier($magConfig);

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

    /** Magazine/DTP profile — editor-parity tags + constrained inline styles */
    public function purifyMagazine(string $html): string
    {
        return @$this->magazinePurifier->purify($html);
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
            if (is_array($value)) {
                // Recurse: nested arrays (accordion/catalog items, columns,
                // rows, …) previously bypassed sanitization entirely and their
                // HTML reached {!! !!} raw — a stored-XSS hole.
                $sanitized[$key] = $this->sanitizeNested($value);
                continue;
            }
            if (!is_string($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            // Fields that should allow rich HTML
            if ($key === 'content' && $allowedHtml !== '') {
                $sanitized[$key] = @$this->purifier->purify($value);
            } elseif ($blockAllowHtml && in_array($key, $inlineHtmlFields)) {
                // Safe inline HTML with NO attributes (event handlers stripped).
                $sanitized[$key] = @$this->inlinePurifier->purify($value);
            } else {
                // Strip all HTML for plain text fields
                $sanitized[$key] = @$this->strictPurifier->purify($value);
            }
        }

        return $sanitized;
    }

    /**
     * Recursively sanitize a nested data structure. String leaves whose key is
     * an HTML-bearing field (content, html, …) are rich-purified (scripts,
     * event handlers, and javascript: URLs removed but safe formatting kept);
     * every other string is stripped of all HTML.
     */
    private function sanitizeNested(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->sanitizeNested($value);
            } elseif (is_string($value)) {
                $out[$key] = in_array($key, self::HTML_LEAF_KEYS, true)
                    ? @$this->purifier->purify($value)
                    : @$this->strictPurifier->purify($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
