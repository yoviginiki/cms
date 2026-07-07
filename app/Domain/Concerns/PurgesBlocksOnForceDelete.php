<?php

namespace App\Domain\Concerns;

/**
 * Audit FIX-A3a — polymorphic `blocks` have no DB FK on blockable, so when a
 * blockable (Page/Post/Slider/ThemeTemplate) is PERMANENTLY removed its blocks
 * would be orphaned forever. Soft-delete keeps the row (and its blocks, for
 * restore); only force-delete purges the blocks.
 *
 * Requires the using model to expose a `blocks()` morphMany relation.
 */
trait PurgesBlocksOnForceDelete
{
    public static function bootPurgesBlocksOnForceDelete(): void
    {
        static::forceDeleting(function ($model) {
            $model->blocks()->delete();
        });
    }
}
