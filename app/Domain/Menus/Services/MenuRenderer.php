<?php

namespace App\Domain\Menus\Services;

use App\Models\Menu;
use App\Models\Site;

class MenuRenderer
{
    public function render(?Menu $menu, Site $site, string $ariaLabel = 'Main navigation'): string
    {
        if (!$menu) return '';

        $menu->load(['rootItems.children.children', 'rootItems.page', 'rootItems.post', 'rootItems.category']);

        $items = $menu->rootItems;
        if ($items->isEmpty()) return '';

        $settings = $site->settings ?? [];
        $style = $menu->style ?? [];
        $isFooter = $menu->location === 'footer';

        if ($isFooter) {
            return $this->renderFooter($menu, $site, $items, $settings, $style, $ariaLabel);
        }

        return $this->renderHeader($menu, $site, $items, $settings, $style, $ariaLabel);
    }

    public function renderByLocation(Site $site, string $location): string
    {
        $menu = $site->menus()->where('location', $location)->first();
        $label = match ($location) {
            'header' => 'Main navigation',
            'footer' => 'Footer navigation',
            'sidebar' => 'Sidebar navigation',
            default => ucfirst($location) . ' navigation',
        };

        return $this->render($menu, $site, $label);
    }

    private function renderHeader(Menu $menu, Site $site, $items, array $settings, array $style, string $ariaLabel): string
    {
        $siteName = e($site->name);
        $logoUrl = $settings['logo_url'] ?? '';
        $scopeClass = 'menu-' . substr(md5($menu->id), 0, 8);

        // Build style CSS from menu style settings
        $bgColor = $style['bgColor'] ?? '';
        $textColor = $style['textColor'] ?? '';
        $hoverColor = $style['hoverColor'] ?? 'var(--color-accent)';
        $fontSize = $style['fontSize'] ?? '';
        $fontWeight = $style['fontWeight'] ?? '';
        $height = $style['height'] ?? '';
        $gap = $style['gap'] ?? '';
        $letterSpacing = $style['letterSpacing'] ?? '';
        $textTransform = $style['textTransform'] ?? '';
        $isSticky = !empty($style['sticky']);
        $isTransparent = !empty($style['transparent']);
        $showSearch = !empty($style['showSearch']);
        $showSocial = !empty($style['showSocial']);

        // Scoped CSS for menu styling
        $css = "<style>\n";
        $css .= ".{$scopeClass} .menu-item{position:relative;}\n";
        $css .= ".{$scopeClass} .menu-top-link,.{$scopeClass} .menu-custom-link{";
        $css .= "font-size:" . ($fontSize ?: '0.875rem') . ";";
        if ($textColor) $css .= " color:{$textColor};";
        else $css .= " color:var(--color-text-muted,#64748b);";
        $css .= "text-decoration:none;transition:color 0.2s,background 0.2s;";
        if ($fontWeight) $css .= "font-weight:{$fontWeight};";
        if ($letterSpacing) $css .= "letter-spacing:{$letterSpacing};";
        if ($textTransform) $css .= "text-transform:{$textTransform};";
        $css .= "}\n";
        $css .= ".{$scopeClass} .menu-top-link:hover,.{$scopeClass} .menu-custom-link:hover{color:{$hoverColor};}\n";

        // Submenu styles
        $css .= ".{$scopeClass} .menu-submenu{display:none;position:absolute;top:100%;left:-12px;min-width:180px;padding:8px 0;";
        $css .= "background:#fff;border:1px solid var(--color-border-light,#eee);box-shadow:0 8px 32px rgba(0,0,0,0.08);border-radius:8px;z-index:100;list-style:none;}\n";
        $css .= ".{$scopeClass} .menu-item:hover > .menu-submenu{display:block;}\n";
        $css .= ".{$scopeClass} .menu-submenu a{display:block;padding:6px 16px;font-size:0.8125rem;white-space:nowrap;}\n";
        $css .= ".{$scopeClass} .menu-submenu a:hover{background:var(--color-bg-alt,#f5f5f0);}\n";

        // Hamburger
        $css .= ".{$scopeClass} .menu-hamburger{display:none;background:none;border:none;cursor:pointer;padding:8px;flex-direction:column;gap:5px;}\n";
        $css .= ".{$scopeClass} .menu-hamburger span{display:block;width:20px;height:1.5px;background:" . ($textColor ?: 'var(--color-text)') . ";transition:transform 0.3s,opacity 0.3s;}\n";
        $css .= ".{$scopeClass}.menu-open .menu-hamburger span:nth-child(1){transform:translateY(6.5px) rotate(45deg);}\n";
        $css .= ".{$scopeClass}.menu-open .menu-hamburger span:nth-child(2){opacity:0;}\n";
        $css .= ".{$scopeClass}.menu-open .menu-hamburger span:nth-child(3){transform:translateY(-6.5px) rotate(-45deg);}\n";

        // Mobile panel
        $css .= ".{$scopeClass} .menu-hamburger-panel{display:none;position:absolute;top:100%;left:0;right:0;flex-direction:column;background:" . ($bgColor ?: 'rgba(255,255,255,0.97)') . ";";
        $css .= "backdrop-filter:blur(20px);border-bottom:1px solid var(--color-border-light);padding:16px;gap:0;box-shadow:0 8px 32px rgba(0,0,0,0.06);}\n";
        $css .= ".{$scopeClass}.menu-open .menu-hamburger-panel{display:flex!important;}\n";
        $css .= ".{$scopeClass} .menu-hamburger-panel a{display:block;padding:10px 16px;}\n";

        $css .= "@media(max-width:768px){.{$scopeClass} .menu-hamburger{display:flex!important;}.{$scopeClass} .menu-desktop{display:none!important;}}\n";
        $css .= "</style>\n";

        // Nav HTML
        $navStyle = '';
        if ($isSticky) $navStyle .= 'position:sticky;top:0;z-index:1000;';
        if ($bgColor && !$isTransparent) $navStyle .= "background:{$bgColor};";
        elseif ($isTransparent) $navStyle .= 'background:transparent;';
        else $navStyle .= 'background:rgba(255,255,255,0.88);backdrop-filter:saturate(180%) blur(20px);-webkit-backdrop-filter:saturate(180%) blur(20px);';
        $navStyle .= 'border-bottom:1px solid var(--color-border-light,#eee);';

        $innerStyle = 'display:flex;align-items:center;justify-content:space-between;max-width:var(--container-width,1400px);margin:0 auto;padding:0 var(--container-padding,40px);';
        $innerStyle .= 'height:' . ($height ?: '56px') . ';';

        $html = $css;
        $html .= "<nav class=\"site-nav {$scopeClass}\" style=\"{$navStyle}\" aria-label=\"" . e($ariaLabel) . "\">\n";
        $html .= "  <div style=\"{$innerStyle}\">\n";

        // Logo
        if ($logoUrl) {
            $html .= "    <a href=\"/\" class=\"nav-logo\" style=\"flex-shrink:0;\"><img src=\"" . e($logoUrl) . "\" alt=\"" . $siteName . "\" style=\"height:" . ($height ? 'calc(' . $height . ' - 16px)' : '40px') . ";max-height:48px;width:auto;\" /></a>\n";
        } else {
            $html .= "    <a href=\"/\" class=\"nav-logo\" style=\"font-family:var(--font-heading);font-size:18px;font-weight:400;color:" . ($textColor ?: 'var(--color-text)') . ";text-decoration:none;flex-shrink:0;\">" . $siteName . "</a>\n";
        }

        // Hamburger
        $html .= "    <button class=\"menu-hamburger\" aria-label=\"Toggle menu\" onclick=\"this.closest('.site-nav').classList.toggle('menu-open')\">\n";
        $html .= "      <span></span><span></span><span></span>\n";
        $html .= "    </button>\n";

        // Desktop menu
        $html .= "    <ul class=\"menu-desktop\" style=\"display:flex;align-items:center;gap:" . ($gap ?: '24px') . ";list-style:none;margin:0;padding:0;\">\n";
        foreach ($items as $item) {
            $html .= $this->renderHeaderItem($item, $scopeClass);
        }

        // Social icons in header
        if ($showSocial) {
            $html .= $this->renderSocialIcons($settings, 'header');
        }

        // Search icon
        if ($showSearch) {
            $html .= "      <li><button aria-label=\"Search\" style=\"background:none;border:none;cursor:pointer;opacity:0.6;\" onclick=\"alert('Search coming soon')\">&#128269;</button></li>\n";
        }

        $html .= "    </ul>\n";

        // Mobile panel
        $html .= "    <div class=\"menu-hamburger-panel\">\n";
        foreach ($items as $item) {
            $url = e($item->resolveUrl(''));
            $label = e($item->label);
            $target = $item->target !== '_self' ? ' target="' . e($item->target) . '" rel="noopener"' : '';
            $html .= "      <a href=\"{$url}\"{$target} class=\"menu-custom-link\">{$label}</a>\n";
        }
        $html .= "    </div>\n";

        $html .= "  </div>\n";
        $html .= "</nav>\n";

        return $html;
    }

    private function renderFooter(Menu $menu, Site $site, $items, array $settings, array $style, string $ariaLabel): string
    {
        $siteName = e($site->name);
        $logoUrl = $settings['logo_url'] ?? '';
        $footerText = $settings['footer_text'] ?? '';
        $footerCopyright = $settings['footer_copyright'] ?? ('© ' . date('Y') . ' ' . $siteName);
        $socialLinks = $settings['social_links'] ?? [];

        $bgColor = $style['bgColor'] ?? 'var(--color-bg-inverse,#333)';
        $textColor = $style['textColor'] ?? '#999';
        $hoverColor = $style['hoverColor'] ?? 'var(--color-accent)';

        $html = "<footer style=\"background:{$bgColor};color:{$textColor};padding:var(--space-16,80px) var(--container-padding,30px);\" aria-label=\"" . e($ariaLabel) . "\">\n";
        $html .= "  <div style=\"max-width:var(--container-width,1080px);margin:0 auto;text-align:center;\">\n";

        // Logo or site name
        if ($logoUrl) {
            $html .= "    <a href=\"/\"><img src=\"" . e($logoUrl) . "\" alt=\"{$siteName}\" style=\"height:32px;width:auto;margin:0 auto 1.5rem;display:block;opacity:0.8;\" /></a>\n";
        } else {
            $html .= "    <a href=\"/\" style=\"font-family:var(--font-heading);font-size:1.25rem;color:{$textColor};text-decoration:none;display:block;margin-bottom:1rem;\">{$siteName}</a>\n";
        }

        // Footer text
        if ($footerText) {
            $html .= "    <p style=\"font-size:0.875rem;margin-bottom:1.5rem;max-width:500px;margin-left:auto;margin-right:auto;\">" . e($footerText) . "</p>\n";
        }

        // Footer menu links
        if ($items->isNotEmpty()) {
            $html .= "    <nav style=\"margin-bottom:1.5rem;\">\n";
            $html .= "      <ul style=\"display:flex;flex-wrap:wrap;justify-content:center;gap:1.5rem;list-style:none;padding:0;margin:0;\">\n";
            foreach ($items as $item) {
                $url = e($item->resolveUrl(''));
                $label = e($item->label);
                $html .= "        <li><a href=\"{$url}\" style=\"color:{$textColor};text-decoration:none;font-size:0.875rem;transition:color 0.2s;\" onmouseover=\"this.style.color='{$hoverColor}'\" onmouseout=\"this.style.color='{$textColor}'\">{$label}</a></li>\n";
            }
            $html .= "      </ul>\n";
            $html .= "    </nav>\n";
        }

        // Social links
        if (!empty($socialLinks)) {
            $html .= $this->renderSocialIcons($settings, 'footer');
        }

        // Copyright
        $html .= "    <p style=\"font-size:0.75rem;opacity:0.6;margin-top:1.5rem;\">" . e($footerCopyright) . "</p>\n";

        $html .= "  </div>\n";
        $html .= "</footer>\n";

        return $html;
    }

    private function renderHeaderItem($item, string $scopeClass): string
    {
        $url = e($item->resolveUrl(''));
        $label = e($item->label);
        $target = $item->target !== '_self' ? ' target="' . e($item->target) . '" rel="noopener"' : '';
        $children = $item->children ?? collect();
        $hasChildren = $children->isNotEmpty();

        $html = "      <li class=\"menu-item\">\n";
        $html .= "        <a href=\"{$url}\"{$target} class=\"menu-top-link\">{$label}</a>\n";

        if ($hasChildren) {
            $html .= "        <ul class=\"menu-submenu\">\n";
            foreach ($children as $child) {
                $childUrl = e($child->resolveUrl(''));
                $childLabel = e($child->label);
                $childTarget = $child->target !== '_self' ? ' target="' . e($child->target) . '" rel="noopener"' : '';
                $html .= "          <li><a href=\"{$childUrl}\"{$childTarget} class=\"menu-custom-link\">{$childLabel}</a></li>\n";
            }
            $html .= "        </ul>\n";
        }

        $html .= "      </li>\n";
        return $html;
    }

    private function renderSocialIcons(array $settings, string $context): string
    {
        $socialLinks = $settings['social_links'] ?? [];
        if (empty($socialLinks)) return '';

        $icons = [
            'facebook' => '&#xf09a;', 'twitter' => '&#xf099;', 'instagram' => '&#xf16d;',
            'youtube' => '&#xf167;', 'linkedin' => '&#xf0e1;', 'github' => '&#xf09b;',
            'tiktok' => '&#xe07b;', 'telegram' => '&#xf2c6;',
        ];

        $style = $context === 'footer'
            ? 'display:flex;justify-content:center;gap:1rem;margin:1rem 0;'
            : 'display:flex;align-items:center;gap:0.75rem;';

        $html = "      <li style=\"list-style:none;\"><div style=\"{$style}\">\n";
        foreach ($socialLinks as $platform => $url) {
            if (!$url || $platform === 'email') continue;
            $html .= "        <a href=\"" . e($url) . "\" target=\"_blank\" rel=\"noopener\" aria-label=\"" . e($platform) . "\" style=\"opacity:0.6;transition:opacity 0.2s;text-decoration:none;\" onmouseover=\"this.style.opacity='1'\" onmouseout=\"this.style.opacity='0.6'\">" . ucfirst($platform)[0] . "</a>\n";
        }
        if (!empty($socialLinks['email'])) {
            $html .= "        <a href=\"mailto:" . e($socialLinks['email']) . "\" aria-label=\"Email\" style=\"opacity:0.6;\">@</a>\n";
        }
        $html .= "      </div></li>\n";

        return $html;
    }
}
