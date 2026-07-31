<?php

namespace App\Domain\InlineEdit\Services;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Publishing\Services\SanitizationService;
use App\Models\Block;
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
     * value to store. Unknown, reserved, or deep (dotted) paths and
     * schema-invalid values are rejected with 422 — never silently ignored
     * (Phase 3.2).
     */
    public function sanitizeField(Block $block, string $field, mixed $value): mixed
    {
        if (str_contains($field, '.') || str_starts_with($field, '__') || in_array($field, self::RESERVED_KEYS, true)) {
            throw new HttpException(422, "Field path '{$field}' is not inline-editable.");
        }

        $definition = $this->registry->get($block->type);
        $rules = $definition?->validationRules() ?? [];

        if (!array_key_exists($field, $rules)) {
            throw new HttpException(422, "Unknown field path '{$field}' for block type '{$block->type}'.");
        }

        $validator = Validator::make([$field => $value], [$field => $rules[$field]]);
        if ($validator->fails()) {
            throw new HttpException(422, "Invalid value for '{$field}': " . $validator->errors()->first());
        }

        // Same sanitizer + same per-block config as the render/save path.
        $probe = new Block([
            'type' => $block->type,
            'data' => array_merge($block->data ?? [], [$field => $value]),
        ]);

        return $this->sanitizer->sanitizeBlock($probe)[$field] ?? null;
    }

    /**
     * Apply validated + sanitized field patches to a block's data (in memory).
     *
     * @param  array<int,array{field:string,value:mixed}>  $patches
     * @return array the new data payload
     */
    public function applyPatches(Block $block, array $patches): array
    {
        $data = $block->data ?? [];
        foreach ($patches as $patch) {
            $data[$patch['field']] = $this->sanitizeField($block, $patch['field'], $patch['value']);
        }

        return $data;
    }
}
