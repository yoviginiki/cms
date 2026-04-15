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
        // Rich text purifier
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,u,a[href|target|rel],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,code,pre,table,thead,tbody,tr,th,td,figure,figcaption,img[src|alt],span[class]');
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('Cache.DefinitionImpl', null);
        $this->purifier = new HTMLPurifier($config);

        // Strict purifier (no HTML at all)
        $strictConfig = HTMLPurifier_Config::createDefault();
        $strictConfig->set('HTML.Allowed', '');
        $strictConfig->set('Cache.DefinitionImpl', null);
        $this->strictPurifier = new HTMLPurifier($strictConfig);
    }

    public function sanitizeBlock(Block $block): array
    {
        $data = $block->data ?? [];
        $definition = $this->registry->get($block->type);

        // Get per-block sanitization config
        $sanitizeConfig = $definition?->definition->sanitizationConfig ?? [];
        $allowedHtml = $sanitizeConfig['HTML.Allowed'] ?? '';

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            // Fields that should allow rich HTML
            if ($key === 'content' && $allowedHtml !== '') {
                $sanitized[$key] = $this->purifier->purify($value);
            } else {
                // Strip all HTML for plain text fields
                $sanitized[$key] = $this->strictPurifier->purify($value);
            }
        }

        return $sanitized;
    }
}
