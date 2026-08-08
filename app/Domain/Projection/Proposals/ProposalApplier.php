<?php

namespace App\Domain\Projection\Proposals;

use App\Domain\InlineEdit\Services\InlineEditService;
use App\Models\Block;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Apply an ACCEPTED change proposal to the live editable blocks — the
 * human-gated write-back step of AI Change Proposals.
 *
 * Reuses the inline editor's exact validate + sanitize pipeline
 * ({@see InlineEditService}), so a proposal writes through the same rules a
 * human inline edit does — no second, divergent write path. Writes go to the
 * live `blocks` (the working copy), never to published state; publishing stays
 * a separate act.
 */
class ProposalApplier
{
    public function __construct(private readonly InlineEditService $inline)
    {
    }

    /**
     * @param list<ChangeOp> $ops accepted ops
     * @return array{applied:int,skipped:list<array{address:string,reason:string}>}
     */
    public function apply(Model $blockable, array $ops): array
    {
        $blocks = Block::where('blockable_type', $blockable->getMorphClass())
            ->where('blockable_id', $blockable->id)
            ->get()
            ->keyBy('id');

        // Group ops by their target block (address prefix before '#').
        $byBlock = [];
        foreach ($ops as $op) {
            [$blockId, $path] = array_pad(explode('#', $op->address, 2), 2, null);
            $byBlock[(string) $blockId][] = [$op, $path];
        }

        $applied = 0;
        $skipped = [];

        foreach ($byBlock as $blockId => $blockOps) {
            $block = $blocks->get($blockId);
            if (! $block) {
                foreach ($blockOps as [$op]) {
                    $skipped[] = ['address' => $op->address, 'reason' => 'block not found'];
                }
                continue;
            }

            try {
                $this->inline->assertPatchable($block);
            } catch (HttpException $e) {
                foreach ($blockOps as [$op]) {
                    $skipped[] = ['address' => $op->address, 'reason' => $e->getMessage()];
                }
                continue;
            }

            $data = $block->data ?? [];
            $dirty = false;

            foreach ($blockOps as [$op, $path]) {
                if ($path === null || $path === '') {
                    $skipped[] = ['address' => $op->address, 'reason' => 'op targets a block, not a field'];
                    continue;
                }

                // Optimistic conflict: the content moved since the proposal was made.
                if ($op->before !== Arr::get($block->data ?? [], $path)) {
                    $skipped[] = ['address' => $op->address, 'reason' => 'stale (content changed since the proposal)'];
                    continue;
                }

                try {
                    if ($op->op === ChangeOp::UNSET) {
                        $this->assertNotReserved($op->address, $path);
                        Arr::forget($data, $path);
                    } else {
                        Arr::set($data, $path, $this->inline->sanitizeField($block, $path, $op->after));
                    }
                    $dirty = true;
                    $applied++;
                } catch (HttpException $e) {
                    $skipped[] = ['address' => $op->address, 'reason' => $e->getMessage()];
                }
            }

            if ($dirty) {
                $block->data = $data;
                $block->save();
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    private function assertNotReserved(string $address, string $path): void
    {
        foreach (explode('.', $path) as $segment) {
            if ($segment === '' || str_starts_with($segment, '__')) {
                throw new HttpException(422, "Field path '{$path}' is not editable.");
            }
        }
    }
}
