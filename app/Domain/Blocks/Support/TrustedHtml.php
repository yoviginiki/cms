<?php

namespace App\Domain\Blocks\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Trusted-HTML capability (F05, audit 2026-09-22).
 *
 * Some content is rendered verbatim — no sanitizer — at publish AND in the
 * admin-origin preview: html-embed blocks and page.raw_html. Authoring it is
 * therefore an admin capability, and the rule must hold on EVERY write path
 * (blocks sync, raw_html, version restore, inline edit), not just one form
 * request. An editor may still save a page that already carries an
 * admin-authored embed — as long as the embed itself is left as it is (or
 * removed); introducing or changing executable HTML is refused.
 */
final class TrustedHtml
{
    /** Block types whose data is emitted without sanitization. */
    public const BLOCK_TYPES = ['html-embed'];

    public static function isTrustedType(?string $type): bool
    {
        return $type !== null && in_array($type, self::BLOCK_TYPES, true);
    }

    public static function mayAuthor(?User $user): bool
    {
        return $user !== null && $user->hasMinimumRole('admin');
    }

    /**
     * The raw HTML payloads of every trusted block in a tree (recursive),
     * as a multiset keyed by content.
     *
     * @return array<string,int>
     */
    public static function payloads(array $tree): array
    {
        $out = [];
        foreach ($tree as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (self::isTrustedType($node['type'] ?? null)) {
                $html = (string) (($node['data'] ?? [])['html'] ?? '');
                $out[$html] = ($out[$html] ?? 0) + 1;
            }
            if (!empty($node['children']) && is_array($node['children'])) {
                foreach (self::payloads($node['children']) as $html => $n) {
                    $out[$html] = ($out[$html] ?? 0) + $n;
                }
            }
        }

        return $out;
    }

    /**
     * Refuse (403) when a non-admin write would introduce or change trusted
     * HTML: every trusted payload in the incoming tree must already exist in
     * the current tree (removal is fine — it is not executable HTML).
     *
     * @throws AuthorizationException
     */
    public static function assertMayWriteTree(?User $user, array $incoming, array $existing): void
    {
        if (self::mayAuthor($user)) {
            return;
        }
        $have = self::payloads($existing);
        foreach (self::payloads($incoming) as $html => $count) {
            if (($have[$html] ?? 0) < $count) {
                throw new AuthorizationException('Only an admin can add or change a raw HTML embed block.');
            }
        }
    }

    /**
     * Refuse (403) when a non-admin sets or changes raw page HTML. Sending
     * the unchanged value back (editor round trips) is allowed; omitting the
     * field entirely is the caller's job to treat as "leave untouched".
     *
     * @throws AuthorizationException
     */
    public static function assertMayWriteRaw(?User $user, ?string $incoming, ?string $existing): void
    {
        if (self::mayAuthor($user)) {
            return;
        }
        if ((string) $incoming === (string) $existing) {
            return;
        }
        throw new AuthorizationException('Only an admin can set or change the raw HTML of a page.');
    }
}
