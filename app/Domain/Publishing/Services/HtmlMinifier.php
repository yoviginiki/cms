<?php
namespace App\Domain\Publishing\Services;

class HtmlMinifier
{
    public function minify(string $html): string
    {
        // Preserve content in pre, code, script, style tags
        $preserved = [];
        $html = preg_replace_callback(
            '#(<(?:pre|code|script|style|textarea)\b[^>]*>)(.*?)(</(?:pre|code|script|style|textarea)>)#si',
            function ($matches) use (&$preserved) {
                $key = '<!--PRESERVED:' . count($preserved) . '-->';
                $preserved[$key] = $matches[0];
                return $key;
            },
            $html
        );

        // Remove HTML comments (except IE conditionals and preserved markers)
        $html = preg_replace('/<!--(?!\[|PRESERVED:).*?-->/s', '', $html);

        // Collapse whitespace between tags
        $html = preg_replace('/>\s+</', '> <', $html);

        // Collapse multiple spaces/newlines into single space
        $html = preg_replace('/\s{2,}/', ' ', $html);

        // Trim lines
        $html = trim($html);

        // Restore preserved content
        foreach ($preserved as $key => $content) {
            $html = str_replace($key, $content, $html);
        }

        return $html;
    }
}
