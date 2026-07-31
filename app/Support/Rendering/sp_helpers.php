<?php

use App\Domain\Publishing\Rendering\RenderContext;

if (!function_exists('sp_editable')) {
    /**
     * Emit inline-edit addressing attributes for a single editable element.
     *
     * The ONLY place data-sp-* attributes are produced. In RenderMode::Publish
     * this returns an empty string (no bytes, no leading space), so published
     * output is byte-identical whether or not a partial calls it. In
     * RenderMode::Edit it returns a leading-space-prefixed attribute string,
     * so it can be dropped straight inside an opening tag:
     *
     *     <h1{!! sp_editable($__blockId, 'text', 'text') !!} style="...">
     *
     * @param  string  $blockId  block uuid (from the $__blockId render var)
     * @param  string  $path     canonical dot-path into blocks.data (e.g. 'text', 'content')
     * @param  string  $type     text|richtext|number|image|url
     * @param  bool    $locked   true => element is read-only for the current user
     */
    function sp_editable(string $blockId, string $path, string $type, bool $locked = false): string
    {
        /** @var RenderContext $ctx */
        $ctx = app(RenderContext::class);

        if (!$ctx->isEdit()) {
            return '';
        }

        $attrs = sprintf(
            ' data-sp-block="%s" data-sp-field="%s" data-sp-type="%s"',
            e($blockId),
            e($path),
            e($type),
        );

        if ($locked) {
            $attrs .= ' data-sp-locked="1"';
        }

        return $attrs;
    }
}
