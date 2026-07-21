<?php

namespace App\Domain\Collections\Services;

use App\Models\Record;
use App\Models\RecordRevision;
use Illuminate\Support\Str;

/**
 * Append-only record history: one snapshot per save/delete/restore, pruned to
 * the newest KEEP entries per record. Snapshots capture data AND relations so
 * a restore rebuilds the full record state through RecordService::save.
 */
class RecordRevisionService
{
    public const KEEP = 20;

    public function snapshot(Record $record, string $event, ?string $userId = null): void
    {
        RecordRevision::create([
            'site_id' => $record->site_id,
            'record_id' => $record->id,
            'event' => $event,
            'title' => mb_substr($record->title ?? '', 0, 500),
            'slug' => mb_substr($record->slug ?? '', 0, 500),
            'status' => $record->status,
            'data' => $record->data ?? [],
            'relations' => $this->relationsSnapshot($record),
            'user_id' => $userId,
            'created_at' => now(),
        ]);

        $this->prune($record->id);
    }

    /** relation_key => [{id, pivot}] in position order — RecordService::save input shape. */
    private function relationsSnapshot(Record $record): array
    {
        $out = [];
        foreach ($record->relationsOut()->orderBy('position')->get(['relation_key', 'to_record_id', 'pivot']) as $edge) {
            $out[$edge->relation_key][] = array_filter([
                'id' => $edge->to_record_id,
                'pivot' => $edge->pivot ?: null,
            ]);
        }

        return $out;
    }

    private function prune(string $recordId): void
    {
        $cutoff = RecordRevision::where('record_id', $recordId)
            ->orderByDesc('created_at')
            ->skip(self::KEEP)
            ->value('created_at');

        if ($cutoff) {
            RecordRevision::where('record_id', $recordId)
                ->where('created_at', '<=', $cutoff)
                ->delete();
        }
    }

    /** Restore input for RecordService::save from a stored revision. */
    public function restoreInput(RecordRevision $revision): array
    {
        return [
            'data' => $revision->data ?? [],
            'relations' => $revision->relations ?? [],
            'status' => $revision->status,
            'slug' => $revision->slug,
        ];
    }
}
