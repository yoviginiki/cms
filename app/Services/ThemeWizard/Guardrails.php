<?php

namespace App\Services\ThemeWizard;

/**
 * The Theme Wizard's "inspired, not copied" guardrails (T3 W5) — one canonical
 * block of rules shared by every AI engine (vision, nudge, conversation) so the
 * policy is consistent and hardened in a single place.
 *
 * Two of these rules are ALSO enforced structurally, not just by prompt:
 *  - Fonts: the output profile names type by CHARACTER; TokenProfileCompiler
 *    substitutes an open font from FontAllowlist, so a reference site's licensed
 *    font can never reach the theme regardless of what the model says.
 *  - Imagery / copy: TokenProfileSchema has NO fields for images, logos, or
 *    body text — the wizard only ever emits design tokens, so a reference's
 *    assets or wording cannot be reproduced.
 * The prompt rules below reinforce these and add the judgement calls a schema
 * can't (hue shifting, not cloning exact brand hexes).
 */
class Guardrails
{
    /** Shared system-prompt block appended by every wizard engine. */
    public static function block(): string
    {
        return <<<RULES
INSPIRED, NOT COPIED — these rules are absolute:
- Capture the FEELING, never the pixels. The result must read as a distinct sibling of any reference, not a clone.
- Shift the hues. Nudge the primary/accent 8–18° in hue and adjust lightness/saturation so the palette is clearly your own; never reproduce a reference's exact brand hex.
- Describe type by CHARACTER only (e.g. "high-contrast editorial serif", "condensed grotesque") — never a font name. The platform substitutes an open-licensed font of that character; you are not choosing or copying anyone's typeface.
- Never reproduce or describe a reference's logo, imagery, illustrations, photography, icons, or wording. You output design TOKENS only.
- Keep it readable (body text clearly contrasts the background) and distinct (a visible brand plus a separate accent).
- Do not imitate a recognizable, trademarked brand system so closely that it could be mistaken for that brand.
RULES;
    }
}
