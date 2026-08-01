<?php

namespace App\Domain\Publishing\Rendering;

/**
 * Ambient render context carried through the block render pipeline.
 *
 * Bound as a singleton. The publish/deploy path never touches it, so it stays
 * at its RenderMode::Publish default and the inline-edit helper emits nothing —
 * keeping published output bit-identical. Only the preview controller (after a
 * policy check) flips it to Edit, always via runIn() so the previous mode is
 * restored no matter what.
 */
class RenderContext
{
    private RenderMode $mode = RenderMode::Publish;

    public function mode(): RenderMode
    {
        return $this->mode;
    }

    public function isEdit(): bool
    {
        return $this->mode === RenderMode::Edit;
    }

    public function set(RenderMode $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Run $callback with the given mode active, restoring the previous mode
     * afterwards even if the callback throws.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function runIn(RenderMode $mode, callable $callback): mixed
    {
        $previous = $this->mode;
        $this->mode = $mode;

        try {
            return $callback();
        } finally {
            $this->mode = $previous;
        }
    }
}
