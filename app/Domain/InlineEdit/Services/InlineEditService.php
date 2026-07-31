<?php

namespace App\Domain\InlineEdit\Services;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Publishing\Services\SanitizationService;
use App\Models\Block;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Pure validation / locking / sanitization logic for inline single-field edits.
 *
 * Deliberately free of DB access so it can be unit-tested without a database.
 * Reuses the EXISTING schema (BlockRegistry / block definitions) and the
 * EXISTING sanitizer (SanitizationService) — no new validators, no new
 * sanitizers (Phase 3.2).
 */
class InlineEditService
{
    /**
     * Blocks whose purpose is to embed a reusable SHARED entity (slider, menu,
     * global section). Editing them means editing the shared entity, which the
     * inline editor must not do — they carry the 'embeds' reference kind and are
     * edited in the library. See docs/inline-edit-scope.md (Phase 3.4).
     */
    private const SHARED_ENTITY_BLOCKS = ['slider_ref', 'global_ref', 'menu', 'global_section'];

    /**
     * Reserved data keys carrying structure/settings, not inline content. These
     * belong to the Page Editor, never to an inline field patch.
     */
    private const RESERVED_KEYS = ['__style', '__animation', '__responsive', '__advanced'];

    public function __construct(
        private BlockRegistry $registry,
        private SanitizationService $sanitizer,
    ) {
    }

    /** Stable per-block content hash — the optimistic-locking handle. */
    public function blockHash(Block $block): string
    {
        return sha1(json_encode($block->data ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Reject a patch to a block that embeds a shared entity (403). */
    public function assertPatchable(Block $block): void
    {
        if (in_array($block->type, self::SHARED_ENTITY_BLOCKS, true)) {
            throw new HttpException(403, "Block '{$block->type}' references a shared entity; edit it in the library.");
        }
    }

    /** Optimistic lock: reject if the block changed since the session hash (409). */
    public function assertHashMatches(Block $block, ?string $expectedHash): void
    {
        if ($expectedHash !== null && $expectedHash !== $this->blockHash($block)) {
            throw new HttpException(409, 'This block changed since you started editing. Reload to get the latest version.');
        }
    }

    /**
     * Validate one field patch against the block schema and return the SANITIZED
     * value to store. Supports flat keys ('text') and nested dot-paths into
     * repeatable items ('items.0.title'). Unknown, reserved, or schema-invalid
     * paths/values are rejected with 422 — never silently ignored (Phase 3.2).
     */
    public function sanitizeField(Block $block, string $field, mixed $value): mixed
    {
        // Reserved/structural segments are never inline-editable.
        foreach (explode('.', $field) as $segment) {
            if ($segment === '' || str_starts_with($segment, '__') || in_array($segment, self::RESERVED_KEYS, true)) {
                throw new HttpException(422, "Field path '{$field}' is not inline-editable.");
            }
        }

        $definition = $this->registry->get($block->type);
        $rules = $definition?->validationRules() ?? [];

        // A concrete path (items.0.title) validates against its schema wildcard
        // pattern (items.*.title).
        $pattern = implode('.', array_map(
            static fn (string $seg): string => ctype_digit($seg) ? '*' : $seg,
            explode('.', $field),
        ));

        if (!array_key_exists($pattern, $rules)) {
            throw new HttpException(422, "Unknown field path '{$field}' for block type '{$block->type}'.");
        }

        $validator = Validator::make(['value' => $value], ['value' => $rules[$pattern]]);
        if ($validator->fails()) {
            throw new HttpException(422, "Invalid value for '{$field}': " . $validator->errors()->first());
        }

        // Sanitize through the SAME pipeline: set the value at the path in a
        // probe block and read back the sanitized leaf.
        $probeData = $block->data ?? [];
        Arr::set($probeData, $field, $value);
        $probe = new Block(['type' => $block->type, 'data' => $probeData]);

        return Arr::get($this->sanitizer->sanitizeBlock($probe), $field);
    }

    /**
     * Apply validated + sanitized field patches to a block's data (in memory).
     * Dot-paths set nested leaves; flat keys set top-level fields.
     *
     * @param  array<int,array{field:string,value:mixed}>  $patches
     * @return array the new data payload
     */
    public function applyPatches(Block $block, array $patches): array
    {
        $data = $block->data ?? [];
        foreach ($patches as $patch) {
            Arr::set($data, $patch['field'], $this->sanitizeField($block, $patch['field'], $patch['value']));
        }

        return $data;
    }
}
