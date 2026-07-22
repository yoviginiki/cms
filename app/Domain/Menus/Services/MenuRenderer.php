<?php

namespace App\Domain\Menus\Services;

use App\Models\Menu;
use App\Models\Site;

class MenuRenderer
{
    private string $menuBaseUrl = '';

    /** Sanitize a CSS value — whitelist safe characters only. */
    private static function safeCss(string $v): string
    {
        $v = trim($v);
        if (!$v) return '';
        // Block dangerous patterns
        if (preg_match('/expression|javascript|url\s*\(|import|\\\\|@/i', $v)) return '';
        // Only allow: letters, digits, #, (), comma, dot, space, %, /, -, single quotes
        return preg_replace("/[^a-zA-Z0-9#(),.'\\s%\\/-]/", '', $v);
    }

    /** Sanitize a URL — only allow http/https/mailto/data schemes. */
    private static function safeUrl(string $v): string
    {
        $v = trim($v);
        if (!$v) return '';
        if (preg_match('#^(https?://|mailto:|/|data:image/)#i', $v)) return $v;
        return '';
    }

    public function render(?Menu $menu, Site $site, string $ariaLabel = 'Main navigation'): string
    {
        if (!$menu) return '';
        $this->menuBaseUrl = $this->getMenuBaseUrl($site);

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
        $logoUrl = self::safeUrl($settings['logo_url'] ?? '');
        $scopeClass = 'menu-' . substr(md5($menu->id), 0, 8);

        // Build style CSS from menu style settings (all values sanitized)
        $bgColor = self::safeCss($style['bgColor'] ?? '');
        $textColor = self::safeCss($style['textColor'] ?? '');
        $hoverColor = self::safeCss($style['hoverColor'] ?? 'var(--color-accent)');
        $fontSize = self::safeCss($style['fontSize'] ?? '');
        $fontWeight = self::safeCss($style['fontWeight'] ?? '');
        $height = self::safeCss($style['height'] ?? '');
        $gap = self::safeCss($style['gap'] ?? '');
        $letterSpacing = self::safeCss($style['letterSpacing'] ?? '');
        $textTransform = self::safeCss($style['textTransform'] ?? '');
        $isSticky = !empty($style['sticky']);
        $isTransparent = !empty($style['transparent']);
        $showSearch = !empty($style['showSearch']);
        $showSocial = !empty($style['showSocial']);

        // Scoped CSS for menu styling
        $css = "<style>\n";
        $css .= ".{$scopeClass} .menu-item{position:relative;}\n";
        $css .= ".{$scopeClass} .menu-top-link,.{$scopeClass} .menu-custom-link{";
        $css .= "font-family:var(--font-heading,sans-serif);";
        $css .= "font-size:" . ($fontSize ?: 'var(--nav-font-size,12px)') . ";";
        $css .= "font-weight:" . ($fontWeight ?: 'var(--nav-font-weight,500)') . ";";
        $css .= "letter-spacing:" . ($letterSpacing ?: 'var(--nav-tracking,0.16em)') . ";";
        $css .= "text-transform:" . ($textTransform ?: 'var(--nav-transform,uppercase)') . ";";
        if ($textColor) $css .= "color:{$textColor};";
        else $css .= "color:var(--color-text-muted,#666);";
        $css .= "text-decoration:none;transition:color 0.2s;";
        $css .= "}\n";
        $css .= ".{$scopeClass} .menu-top-link:hover,.{$scopeClass} .menu-custom-link:hover{color:{$hoverColor};}\n";

        // Submenu styles
        $css .= ".{$scopeClass} .menu-submenu{display:none;position:absolute;top:100%;left:-12px;min-width:180px;padding:8px 0;";
        $css .= "background:var(--color-bg,#fff);border:1px solid var(--color-border-light,#eee);box-shadow:var(--shadow-md,0 8px 32px rgba(0,0,0,0.08));border-radius:var(--border-radius-md,0.5rem);z-index:100;list-style:none;}\n";
        $css .= ".{$scopeClass} .menu-item:hover > .menu-submenu{display:block;}\n";
        $css .= ".{$scopeClass} .menu-submenu a{display:block;padding:6px 16px;font-size:0.8125rem;white-space:nowrap;}\n";
        $css .= ".{$scopeClass} .menu-submenu a:hover{background:var(--color-bg-alt,#f5f5f0);}\n";

        // Hamburger
        $css .= ".{$scopeClass} .menu-hamburger{display:none;background:none;border:none;cursor:pointer;padding:8px;flex-direction:column;gap:5px;}\n";
        $css .= ".{$scopeClass} .menu-hamburger span{display:block;width:20px;height:1.5px;background:" . ($textColor ?: 'var(--color-text)') . ";transition:transform 0.3s,opacity 0.3s;}\n";
        $css .= ".{$scopeClass}.menu-open .menu-hamburger span:nth-child(1){transform:translateY(6.5px) rotate(45deg);}\n";
        $css .= ".{$scopeClass}.menu-open .menu-hamburger span:nth-child(2){opacity:0;}\n";
        $css .= ".{$scopeClass}.menu-open .menu-hamburger span:nth-child(3){transform:translateY(-6.5px) rotate(-45deg);}\n";

        // Mobile panel — uses dedicated mobile settings
        $mobileBg = self::safeCss($style['mobileBgColor'] ?? '') ?: ($bgColor ?: 'var(--color-bg,#ffffff)');
        $mobileFontSize = self::safeCss($style['mobileFontSize'] ?? '') ?: '16px';
        $mobileBreakpoint = in_array((string) ($style['mobileBreakpoint'] ?? '768'), ['480', '768', '1024', '9999']) ? ($style['mobileBreakpoint'] ?? '768') : '768';

        $css .= ".{$scopeClass} .menu-hamburger-panel{display:none;position:absolute;top:100%;left:0;right:0;flex-direction:column;";
        $css .= "background:{$mobileBg};border-bottom:1px solid var(--color-border-light);padding:8px 0;gap:0;box-shadow:0 8px 32px rgba(0,0,0,0.1);}\n";
        $css .= ".{$scopeClass}.menu-open .menu-hamburger-panel{display:flex!important;}\n";
        $css .= ".{$scopeClass} .menu-hamburger-panel a{display:block;padding:12px 24px;font-size:{$mobileFontSize};border-bottom:1px solid var(--color-border-light,#eee);}\n";
        $css .= ".{$scopeClass} .menu-hamburger-panel a:last-child{border-bottom:none;}\n";

        $css .= "@media(max-width:{$mobileBreakpoint}px){.{$scopeClass} .menu-hamburger{display:flex!important;}.{$scopeClass} .menu-desktop{display:none!important;}}\n";
        $css .= "</style>\n";

        // Nav HTML — always sticky with solid background for mobile reliability
        $navStyle = 'position:sticky;top:0;z-index:1000;';
        if ($bgColor && !$isTransparent) {
            $navStyle .= "background:{$bgColor};";
        } elseif ($isTransparent) {
            $navStyle .= 'background:transparent;';
        } else {
            $navStyle .= 'background:var(--color-bg,#ffffff);';
        }
        $navStyle .= 'border-bottom:1px solid var(--color-border-light,#eee);';

        $innerStyle = 'display:flex;align-items:center;justify-content:space-between;max-width:var(--container-width,1200px);width:90%;margin:0 auto;padding:var(--nav-padding,14px 0);';
        if ($height) {
            $innerStyle .= 'height:' . $height . ';';
        }

        $html = $css;
        // Check if theme uses overlay nav mode
        $navMode = $site->theme?->config['tokens']['nav-mode'] ?? '';
        $overlayClass = $navMode === 'overlay' ? ' site-nav--overlay' : '';
        $html .= "<nav class=\"site-nav{$overlayClass} {$scopeClass}\" style=\"{$navStyle}\" aria-label=\"" . e($ariaLabel) . "\">\n";
        $html .= "  <div style=\"{$innerStyle}\">\n";

        // Logo (optionally followed by the site name, WordPress-style branding)
        if ($logoUrl) {
            $nameSpan = '';
            if (!empty($settings['logo_show_name'])) {
                $nameSpan = "<span style=\"font-family:var(--font-heading,sans-serif);font-size:var(--nav-logo-size,14px);font-weight:var(--nav-logo-weight,600);color:" . ($textColor ?: 'var(--color-text)') . ";letter-spacing:var(--nav-logo-tracking,0.1em);text-transform:var(--nav-logo-transform,none);\">" . $siteName . '</span>';
            }
            $html .= "    <a href=\"/\" class=\"nav-logo\" style=\"flex-shrink:0;display:flex;align-items:center;gap:10px;text-decoration:none;\"><img src=\"" . e($logoUrl) . "\" alt=\"" . $siteName . "\" style=\"height:" . ($height ? 'calc(' . $height . ' - 16px)' : '40px') . ";max-height:48px;width:auto;\" />{$nameSpan}</a>\n";
        } else {
            $html .= "    <a href=\"/\" class=\"nav-logo\" style=\"font-family:var(--font-heading,sans-serif);font-size:var(--nav-logo-size,14px);font-weight:var(--nav-logo-weight,600);color:" . ($textColor ?: 'var(--color-text)') . ";text-decoration:none;letter-spacing:var(--nav-logo-tracking,0.1em);text-transform:var(--nav-logo-transform,none);flex-shrink:0;\">" . $siteName . "</a>\n";
        }

        // Hamburger
        $html .= "    <button class=\"menu-hamburger\" aria-label=\"Toggle menu\" onclick=\"this.closest('.site-nav').classList.toggle('menu-open')\">\n";
        $html .= "      <span></span><span></span><span></span>\n";
        $html .= "    </button>\n";

        // Desktop menu
        $html .= "    <ul class=\"menu-desktop\" style=\"display:flex;align-items:center;gap:" . ($gap ?: 'var(--nav-gap,28px)') . ";list-style:none;margin:0;padding:0;\">\n";
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
            $url = e($item->resolveUrl($this->menuBaseUrl));
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
        $logoUrl = self::safeUrl($settings['logo_url'] ?? '');
        $footerText = $settings['footer_text'] ?? '';
        $footerCopyright = $settings['footer_copyright'] ?? ('© ' . date('Y') . ' ' . $siteName);
        $socialLinks = $settings['social_links'] ?? [];

        $bgColor = self::safeCss($style['bgColor'] ?? 'var(--color-bg-inverse,#333)');
        $textColor = self::safeCss($style['textColor'] ?? '#999');
        $hoverColor = self::safeCss($style['hoverColor'] ?? 'var(--color-accent)');
        $scopeClass = 'footer-' . substr(md5($menu->id), 0, 8);

        $html = "<style>.{$scopeClass} a{color:{$textColor};transition:color 0.2s;}.{$scopeClass} a:hover{color:{$hoverColor};}</style>\n";
        $html .= "<footer class=\"{$scopeClass}\" style=\"background:{$bgColor};color:{$textColor};padding:var(--space-16,80px) var(--container-padding,30px);\" aria-label=\"" . e($ariaLabel) . "\">\n";
        $html .= "  <div style=\"max-width:var(--container-width,1080px);margin:0 auto;text-align:center;\">\n";

        // Logo or site name
        if ($logoUrl) {
            $html .= "    <a href=\"/\"><img src=\"" . e($logoUrl) . "\" alt=\"{$siteName}\" style=\"height:32px;width:auto;margin:0 auto 1.5rem;display:block;opacity:0.8;\" /></a>\n";
        } else {
            $html .= "    <a href=\"/\" style=\"font-family:var(--font-heading);font-size:1.25rem;color:{$textColor};text-decoration:none;display:block;margin-bottom:1rem;\">{$siteName}</a>\n";
        }

        // Footer text
        if ($footerText) {
            $html .= "    <p style=\"font-size:var(--font-size-sm,0.875rem);color:var(--color-text-muted,#888);margin-bottom:1.5rem;max-width:500px;margin-left:auto;margin-right:auto;\">" . e($footerText) . "</p>\n";
        }

        // Footer menu links
        if ($items->isNotEmpty()) {
            $html .= "    <nav style=\"margin-bottom:1.5rem;\">\n";
            $html .= "      <ul style=\"display:flex;flex-wrap:wrap;justify-content:center;gap:1.5rem;list-style:none;padding:0;margin:0;\">\n";
            foreach ($items as $item) {
                $url = e($item->resolveUrl($this->menuBaseUrl));
                $label = e($item->label);
                $html .= "        <li><a href=\"{$url}\" style=\"text-decoration:none;font-size:var(--font-size-sm,0.875rem);color:var(--color-text-muted,#888);transition:color 0.2s;\">{$label}</a></li>\n";
            }
            $html .= "      </ul>\n";
            $html .= "    </nav>\n";
        }

        // Social links
        if (!empty($socialLinks)) {
            $html .= $this->renderSocialIcons($settings, 'footer');
        }

        // Copyright
        $html .= "    <p style=\"font-size:0.75rem;color:var(--color-text-muted,#888);margin-top:1.5rem;\">" . e($footerCopyright) . "</p>\n";

        $html .= "  </div>\n";
        $html .= "</footer>\n";

        return $html;
    }

    private function renderHeaderItem($item, string $scopeClass): string
    {
        $url = e($item->resolveUrl($this->menuBaseUrl));
        $label = e($item->label);
        $target = $item->target !== '_self' ? ' target="' . e($item->target) . '" rel="noopener"' : '';
        $children = $item->children ?? collect();
        $hasChildren = $children->isNotEmpty();

        $html = "      <li class=\"menu-item\">\n";
        $html .= "        <a href=\"{$url}\"{$target} class=\"menu-top-link\">{$label}</a>\n";

        if ($hasChildren) {
            $html .= "        <ul class=\"menu-submenu\">\n";
            foreach ($children as $child) {
                $childUrl = e($child->resolveUrl($this->menuBaseUrl));
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
            $safeUrl = self::safeUrl((string) $url);
            if (!$safeUrl) continue;
            $html .= "        <a href=\"" . e($safeUrl) . "\" target=\"_blank\" rel=\"noopener\" aria-label=\"" . e($platform) . "\" style=\"opacity:0.6;transition:opacity 0.2s;text-decoration:none;\">" . e(ucfirst((string) $platform)[0]) . "</a>\n";
        }
        if (!empty($socialLinks['email'])) {
            $html .= "        <a href=\"mailto:" . e($socialLinks['email']) . "\" aria-label=\"Email\" style=\"opacity:0.6;\">@</a>\n";
        }
        $html .= "      </div></li>\n";

        return $html;
    }

    /**
     * Get base URL prefix for menu links.
     * On sys.ensodo.eu (admin preview), prefix with /sites/{slug}.
     * On the actual site domain, return empty string.
     */
    private function getMenuBaseUrl(Site $site): string
    {
        // Only prefix on dynamic site preview route (not during publish/build)
        $route = request()->route();
        if ($route && str_starts_with($route->uri(), 'sites/')) {
            return '/sites/' . ($site->slug ?? $site->id);
        }
        return '';
    }
}
