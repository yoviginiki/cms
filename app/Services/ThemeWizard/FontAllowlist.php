<?php

namespace App\Services\ThemeWizard;

/**
 * Curated open-license (OFL / Apache) Google Fonts the Theme Wizard may use.
 *
 * The wizard never copies a reference site's (often licensed) fonts — it reads
 * the *character* of the type (geometric sans, high-contrast serif, condensed
 * display, …) and substitutes the nearest open font from this list. Every entry
 * is safe to self-host / @import and ship on a published static site.
 *
 * Each font: category (display|body|mono), character tags (for matching a
 * design read), a suggested pairing partner, and a one-line note.
 */
class FontAllowlist
{
    /** @var array<string, array{category:string, character:array<int,string>, pairs_with:string, note:string}> */
    public const FONTS = [
        // ── Display / heading ──
        'Fraunces'            => ['category' => 'display', 'character' => ['serif', 'high-contrast', 'editorial', 'expressive', 'warm'], 'pairs_with' => 'Newsreader', 'note' => 'Soft-serif with optical sizing; editorial warmth.'],
        'Playfair Display'    => ['category' => 'display', 'character' => ['serif', 'high-contrast', 'elegant', 'classic'], 'pairs_with' => 'Source Serif 4', 'note' => 'Didone contrast; luxury / fashion.'],
        'DM Serif Display'    => ['category' => 'display', 'character' => ['serif', 'high-contrast', 'display', 'elegant'], 'pairs_with' => 'Inter', 'note' => 'Tight display serif for big headlines.'],
        'Instrument Serif'    => ['category' => 'display', 'character' => ['serif', 'light', 'editorial', 'quiet'], 'pairs_with' => 'Inter', 'note' => 'Understated single-weight editorial serif.'],
        'Archivo'             => ['category' => 'display', 'character' => ['sans', 'grotesque', 'bold', 'modern', 'tight'], 'pairs_with' => 'Inter', 'note' => 'Grotesque with heavy weights; portfolio/agency.'],
        'Space Grotesk'       => ['category' => 'display', 'character' => ['sans', 'geometric', 'techy', 'modern'], 'pairs_with' => 'Inter', 'note' => 'Slightly quirky geometric; product/tech.'],
        'Syne'                => ['category' => 'display', 'character' => ['sans', 'geometric', 'display', 'unconventional'], 'pairs_with' => 'Inter', 'note' => 'Extravagant display sans; art/culture.'],
        'Bricolage Grotesque' => ['category' => 'display', 'character' => ['sans', 'grotesque', 'quirky', 'contemporary'], 'pairs_with' => 'Work Sans', 'note' => 'Characterful grotesque; editorial/indie.'],
        'Barlow Condensed'    => ['category' => 'display', 'character' => ['sans', 'condensed', 'bold', 'industrial'], 'pairs_with' => 'Barlow', 'note' => 'Condensed; cinematic / Swiss posters.'],
        'Libre Franklin'      => ['category' => 'display', 'character' => ['sans', 'grotesque', 'classic', 'news'], 'pairs_with' => 'Lora', 'note' => 'Franklin-Gothic revival; news/editorial.'],

        // ── Body / reading ──
        'Inter'          => ['category' => 'body', 'character' => ['sans', 'neutral', 'ui', 'grotesque'], 'pairs_with' => 'Space Grotesk', 'note' => 'The default neutral UI sans.'],
        'Figtree'        => ['category' => 'body', 'character' => ['sans', 'geometric', 'friendly', 'rounded'], 'pairs_with' => 'Fraunces', 'note' => 'Friendly geometric; startups/lifestyle.'],
        'Work Sans'      => ['category' => 'body', 'character' => ['sans', 'humanist', 'clean'], 'pairs_with' => 'Bricolage Grotesque', 'note' => 'Optimized for screen text.'],
        'Nunito Sans'    => ['category' => 'body', 'character' => ['sans', 'humanist', 'warm', 'rounded', 'friendly'], 'pairs_with' => 'Fraunces', 'note' => 'Soft humanist; warm/lifestyle.'],
        'Public Sans'    => ['category' => 'body', 'character' => ['sans', 'neutral', 'clean', 'government'], 'pairs_with' => 'Public Sans', 'note' => 'Strict neutral sans; civic/business.'],
        'IBM Plex Sans'  => ['category' => 'body', 'character' => ['sans', 'technical', 'humanist', 'corporate'], 'pairs_with' => 'IBM Plex Mono', 'note' => 'Corporate-technical; engineering.'],
        'Barlow'         => ['category' => 'body', 'character' => ['sans', 'low-contrast', 'neutral'], 'pairs_with' => 'Barlow Condensed', 'note' => 'Barlow Condensed’s body companion.'],
        'Newsreader'     => ['category' => 'body', 'character' => ['serif', 'reading', 'editorial', 'literary'], 'pairs_with' => 'Fraunces', 'note' => 'Comfortable long-form serif.'],
        'Source Serif 4' => ['category' => 'body', 'character' => ['serif', 'reading', 'neutral'], 'pairs_with' => 'Playfair Display', 'note' => 'Even, workhorse reading serif.'],
        'Lora'           => ['category' => 'body', 'character' => ['serif', 'contemporary', 'calm', 'reading'], 'pairs_with' => 'Libre Franklin', 'note' => 'Contemporary serif with brushed curves.'],
        'Spectral'       => ['category' => 'body', 'character' => ['serif', 'editorial', 'refined', 'reading'], 'pairs_with' => 'Inter', 'note' => 'Screen-first serif; magazine body.'],

        // ── Mono ──
        'JetBrains Mono' => ['category' => 'mono', 'character' => ['mono', 'techy', 'code'], 'pairs_with' => 'Inter', 'note' => 'Code/mono accents.'],
        'IBM Plex Mono'  => ['category' => 'mono', 'character' => ['mono', 'technical', 'code'], 'pairs_with' => 'IBM Plex Sans', 'note' => 'Technical mono.'],
        'Space Mono'     => ['category' => 'mono', 'character' => ['mono', 'retro', 'quirky'], 'pairs_with' => 'Space Grotesk', 'note' => 'Retro mono display accent.'],
    ];

    /** Fallback per role when nothing matches the requested character. */
    private const DEFAULTS = ['display' => 'Archivo', 'body' => 'Inter', 'mono' => 'JetBrains Mono'];

    public static function isAllowed(string $name): bool
    {
        return isset(self::FONTS[$name]);
    }

    /** @return array{category:string, character:array<int,string>, pairs_with:string, note:string}|null */
    public static function get(string $name): ?array
    {
        return self::FONTS[$name] ?? null;
    }

    /** @return array<int,string> font names in a given role */
    public static function byCategory(string $category): array
    {
        return array_keys(array_filter(self::FONTS, fn ($f) => $f['category'] === $category));
    }

    /**
     * Best open font for a role given a design read. `$character` is a list of
     * tags or a free phrase ("geometric sans-serif"); scores by tag overlap.
     */
    public static function suggest(string $role, string|array $character): string
    {
        $role = in_array($role, ['display', 'body', 'mono'], true) ? $role : 'body';
        $wanted = is_array($character)
            ? array_map('strtolower', $character)
            : preg_split('/[^a-z]+/', strtolower($character), -1, PREG_SPLIT_NO_EMPTY);

        $best = self::DEFAULTS[$role];
        $bestScore = -1;
        foreach (self::FONTS as $name => $f) {
            if ($f['category'] !== $role) continue;
            $score = count(array_intersect($f['character'], $wanted));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $name;
            }
        }
        return $best;
    }

    /** Suggested pairing partner for a font (falls back to a role default). */
    public static function pairFor(string $name): string
    {
        return self::FONTS[$name]['pairs_with'] ?? self::DEFAULTS['body'];
    }
}
