<?php

namespace App\Domain\Publishing\Rendering;

/**
 * Render mode for the page/block render pipeline.
 *
 * Publish  — the canonical output. Must be byte-for-byte identical to what
 *            ships to a visitor. The inline-edit layer emits NOTHING here.
 * Edit     — preview-only. Editable elements gain data-sp-* addressing
 *            attributes so the overlay runtime can target them. Set ONLY by
 *            the preview controller after a policy check (see Phase 4), never
 *            by a publish/deploy path.
 *
 * Default is always Publish. Edit is opt-in and explicit.
 */
enum RenderMode: string
{
    case Publish = 'publish';
    case Edit = 'edit';
}
