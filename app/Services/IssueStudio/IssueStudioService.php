<?php

namespace App\Services\IssueStudio;

use App\Models\IssueStudio\StudioSession;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates the Issue Studio conversation state machine:
 * interviewing -> flatplanning -> generating -> complete.
 * Phase 3 covers the interview; flatplan/spread phases build on this.
 */
class IssueStudioService
{
    public function __construct(
        private InterviewEngine $interview,
        private FlatplanEngine $flatplan,
        private FlatplanValidator $validator,
        private SpreadEngine $spreadEngine,
        private SpreadComposer $composer,
        private Playbook $playbook,
        private TokenBudget $budget,
    ) {
    }

    public function create(Site $site, User $user, ?string $title = null): StudioSession
    {
        return StudioSession::create([
            'tenant_id' => $user->tenant_id,
            'site_id' => $site->id,
            'user_id' => $user->id,
            'title' => $title,
            'status' => 'interviewing',
            'brief' => [
                'topic' => null,
                'working_title' => null,
                'audience' => null,
                'tone' => null,
                'genre' => null,
                'page_ambition' => null,
                'notes' => [],
                'materials' => [],
            ],
            'transcript' => [],
            'token_usage' => [],
        ]);
    }

    /** One chat turn: user message in, editorial-director reply + brief updates out. */
    public function sendMessage(StudioSession $session, string $content): StudioSession
    {
        if ($session->status !== 'interviewing') {
            throw new RuntimeException('This session is past the interview stage.');
        }

        $this->budget->assertAvailable($session->tenant_id);

        $turn = $this->interview->turn($session, $content);

        $transcript = $session->transcript ?? [];
        $transcript[] = ['role' => 'user', 'text' => $content, 'at' => now()->toIso8601String()];
        $transcript[] = ['role' => 'assistant', 'text' => $turn['reply'], 'at' => now()->toIso8601String()];
        $session->transcript = $transcript;

        $session->brief = $this->applyPatch($session->brief ?? [], $turn['patch']);

        if (!$session->title && !empty($session->brief['working_title'])) {
            $session->title = $session->brief['working_title'];
        }

        $this->recordUsage($session, 'interview', $turn['usage']);

        if ($turn['complete']) {
            $session->status = 'flatplanning';
        }

        $session->save();

        return $session;
    }

    /**
     * Attach material to the brief. Texts carry content inline; images
     * reference an already-uploaded asset (existing asset pipeline).
     */
    public function addMaterial(StudioSession $session, string $kind, string $title, ?string $content, ?string $assetId): StudioSession
    {
        if (!in_array($kind, ['text', 'image', 'interview'], true)) {
            throw new RuntimeException('Unknown material kind.');
        }
        if (in_array($kind, ['text', 'interview'], true) && trim((string) $content) === '') {
            throw new RuntimeException('Text material needs content.');
        }
        if ($kind === 'image' && !$assetId) {
            throw new RuntimeException('Image material needs an asset.');
        }

        $brief = $session->brief ?? [];
        $materials = $brief['materials'] ?? [];

        $material = [
            'id' => 'm-' . Str::lower(Str::random(8)),
            'kind' => $kind,
            'title' => $title !== '' ? $title : ($kind === 'image' ? 'Image' : 'Untitled text'),
            'added_at' => now()->toIso8601String(),
        ];
        if ($kind === 'image') {
            $material['asset_id'] = $assetId;
        } else {
            $material['content'] = (string) $content;
            $material['word_count'] = str_word_count(strip_tags((string) $content));
        }

        $materials[] = $material;
        $brief['materials'] = $materials;
        $session->brief = $brief;
        $session->save();

        return $session;
    }

    public function removeMaterial(StudioSession $session, string $materialId): StudioSession
    {
        $brief = $session->brief ?? [];
        $brief['materials'] = array_values(array_filter(
            $brief['materials'] ?? [],
            fn ($m) => ($m['id'] ?? null) !== $materialId
        ));
        $session->brief = $brief;
        $session->save();

        return $session;
    }

    /** User forces the jump to flatplanning ("just go"). */
    public function completeInterview(StudioSession $session): StudioSession
    {
        if ($session->status !== 'interviewing') {
            return $session;
        }
        if (empty($session->brief['topic'])) {
            throw new RuntimeException('The wizard needs at least a topic before planning.');
        }

        $session->status = 'flatplanning';
        $session->save();

        return $session;
    }

    /** Generate (or regenerate) the flatplan. Only while flatplanning and unapproved. */
    public function generateFlatplan(StudioSession $session): StudioSession
    {
        if ($session->status !== 'flatplanning') {
            throw new RuntimeException('Flatplans are generated after the interview, before approval.');
        }
        if ($session->flatplan['approved'] ?? false) {
            throw new RuntimeException('The flatplan is already approved and locked.');
        }

        $this->budget->assertAvailable($session->tenant_id);

        $result = $this->flatplan->generate($session);
        foreach ($result['usage'] as $usage) {
            $this->recordUsage($session, 'flatplan', $usage);
        }

        $session->flatplan = [
            'spreads' => $result['spreads'],
            'approved' => false,
            'generated_at' => now()->toIso8601String(),
        ];
        $session->save();

        return $session;
    }

    /** Conversational revision of one flatplan slot. */
    public function reviseFlatplanSpread(StudioSession $session, int $position, string $instruction): StudioSession
    {
        $this->assertEditableFlatplan($session);
        $this->budget->assertAvailable($session->tenant_id);

        $result = $this->flatplan->reviseSpread($session, $position, $instruction);
        foreach ($result['usage'] as $usage) {
            $this->recordUsage($session, 'flatplan-revise', $usage);
        }

        $flatplan = $session->flatplan;
        foreach ($flatplan['spreads'] as $i => $spread) {
            if (($spread['position'] ?? null) === $position) {
                $flatplan['spreads'][$i] = $result['spread'];
            }
        }
        $session->flatplan = $flatplan;
        $session->save();

        return $session;
    }

    /**
     * Persist a drag-reorder. $order is the array of CURRENT positions in
     * their new sequence; positions are then rewritten 0..n and re-validated.
     */
    public function reorderFlatplan(StudioSession $session, array $order): StudioSession
    {
        $this->assertEditableFlatplan($session);

        $spreads = $session->flatplan['spreads'];
        $byPosition = collect($spreads)->keyBy('position');

        if (count($order) !== count($spreads) || array_diff($byPosition->keys()->all(), $order) !== []) {
            throw new RuntimeException('Reorder must be a permutation of all current spread positions.');
        }

        $reordered = [];
        foreach (array_values($order) as $newPos => $oldPos) {
            $spread = $byPosition->get($oldPos);
            $spread['position'] = $newPos;
            $reordered[] = $spread;
        }

        $errors = $this->validator->validate($reordered, $session->brief ?? []);
        if ($errors !== []) {
            throw new RuntimeException('That order breaks the issue structure: ' . implode(' | ', $errors));
        }

        $flatplan = $session->flatplan;
        $flatplan['spreads'] = $reordered;
        $session->flatplan = $flatplan;
        $session->save();

        return $session;
    }

    /** Approval locks the flatplan and creates the spread rows. */
    public function approveFlatplan(StudioSession $session): StudioSession
    {
        $this->assertEditableFlatplan($session);

        $flatplan = $session->flatplan;
        $flatplan['approved'] = true;
        $flatplan['approved_at'] = now()->toIso8601String();

        $session->spreads()->delete();
        foreach ($flatplan['spreads'] as $spread) {
            $session->spreads()->create([
                'tenant_id' => $session->tenant_id,
                'position' => $spread['position'],
                'status' => 'pending',
                'working_title' => $spread['working_title'],
                'section' => $spread['section'],
                'pattern' => $spread['pattern'],
                'materials' => $spread['materials'],
                'intent' => $spread['intent'],
            ]);
        }

        $session->flatplan = $flatplan;
        $session->status = 'generating';
        $session->save();

        return $session;
    }

    /** Generate the next pending spread (flatplan order, one at a time). */
    public function generateNextSpread(StudioSession $session): StudioSession
    {
        if ($session->status !== 'generating') {
            throw new RuntimeException('Approve the flatplan before generating spreads.');
        }

        $open = $session->spreads()->whereIn('status', ['generated', 'revising'])->first();
        if ($open) {
            throw new RuntimeException("Decide on spread {$open->position} (keep, revise or rethink) before generating the next one.");
        }

        $spread = $session->spreads()->where('status', 'pending')->orderBy('position')->first();
        if (!$spread) {
            throw new RuntimeException('Every spread is already generated.');
        }

        $this->budget->assertAvailable($session->tenant_id);

        $result = $this->spreadEngine->generate($session, $spread);
        $this->persistSpreadDoc($session, $spread, $result);

        return $session->refresh();
    }

    /** Keep: approve the spread; complete the session when all are approved. */
    public function keepSpread(StudioSession $session, int $position): StudioSession
    {
        $spread = $this->findSpread($session, $position);
        if ($spread->status !== 'generated') {
            throw new RuntimeException('Only a freshly generated spread can be kept.');
        }

        $spread->update(['status' => 'approved']);

        if (!$session->spreads()->where('status', '!=', 'approved')->exists()) {
            $session->status = 'complete';
            $session->save();
        }

        return $session->refresh();
    }

    /** Conversational revision of a generated (or already approved) spread. */
    public function reviseGeneratedSpread(StudioSession $session, int $position, string $instruction): StudioSession
    {
        $spread = $this->findSpread($session, $position);
        if (!in_array($spread->status, ['generated', 'approved'], true)) {
            throw new RuntimeException('This spread has not been generated yet.');
        }

        $this->budget->assertAvailable($session->tenant_id);

        $currentPages = $this->composer->readSpread($session, $spread);
        $result = $this->spreadEngine->revise($session, $spread, $currentPages, $instruction);
        $this->persistSpreadDoc($session, $spread, $result, 'spread-revise');

        // an approved session revising a spread reopens it
        if ($session->status === 'complete') {
            $session->status = 'generating';
            $session->save();
        }

        return $session->refresh();
    }

    /** Rethink: regenerate from scratch, optionally with a different pattern. */
    public function rethinkSpread(StudioSession $session, int $position, ?string $pattern): StudioSession
    {
        $spread = $this->findSpread($session, $position);
        if (!in_array($spread->status, ['generated', 'approved'], true)) {
            throw new RuntimeException('This spread has not been generated yet.');
        }

        if ($pattern !== null && $pattern !== $spread->pattern) {
            $names = $this->playbook->patternNames();
            $allowed = $position === 0 ? $names['covers'] : $names['spreads'];
            if (!in_array($pattern, $allowed, true)) {
                throw new RuntimeException("\"{$pattern}\" is not a valid pattern for this slot.");
            }
            $spread->pattern = $pattern;
            $spread->save();

            // keep the locked flatplan record in sync
            $flatplan = $session->flatplan;
            foreach ($flatplan['spreads'] ?? [] as $i => $fp) {
                if (($fp['position'] ?? null) === $position) {
                    $flatplan['spreads'][$i]['pattern'] = $pattern;
                }
            }
            $session->flatplan = $flatplan;
            $session->save();
        }

        $this->budget->assertAvailable($session->tenant_id);

        $result = $this->spreadEngine->generate($session, $spread);
        $this->persistSpreadDoc($session, $spread, $result, 'spread-rethink');

        if ($session->status === 'complete') {
            $session->status = 'generating';
            $session->save();
        }

        return $session->refresh();
    }

    private function persistSpreadDoc(StudioSession $session, \App\Models\IssueStudio\StudioSpread $spread, array $result, string $phase = 'spread'): void
    {
        foreach ($result['usage'] as $usage) {
            $this->recordUsage($session, $phase, $usage);
        }
        $session->save();

        $pageIds = $this->composer->writeSpread($session, $spread, $result['doc']);

        $notes = $spread->generation_notes ?? [];
        $notes[] = [
            'note' => $result['doc']['editorial_note'] ?? '',
            'pattern' => $spread->pattern,
            'phase' => $phase,
            'at' => now()->toIso8601String(),
        ];

        $spread->update([
            'status' => 'generated',
            'page_ids' => $pageIds,
            'generation_notes' => array_slice($notes, -10),
        ]);

        // ids of every other spread's pages were remapped by the full-replace save
        $this->composer->syncPageIds($session);
    }

    private function findSpread(StudioSession $session, int $position): \App\Models\IssueStudio\StudioSpread
    {
        $spread = $session->spreads()->where('position', $position)->first();
        if (!$spread) {
            throw new RuntimeException("No spread at position {$position}.");
        }

        return $spread;
    }

    private function assertEditableFlatplan(StudioSession $session): void
    {
        if ($session->status !== 'flatplanning' || !is_array($session->flatplan)) {
            throw new RuntimeException('There is no editable flatplan on this session.');
        }
        if ($session->flatplan['approved'] ?? false) {
            throw new RuntimeException('The flatplan is approved and locked.');
        }
    }

    public function abandon(StudioSession $session): void
    {
        $session->status = 'abandoned';
        $session->save();
    }

    /** Merge a brief_patch: only non-null values land; notes accumulate. */
    private function applyPatch(array $brief, array $patch): array
    {
        foreach (['topic', 'working_title', 'audience', 'tone'] as $field) {
            if (isset($patch[$field]) && is_string($patch[$field]) && $patch[$field] !== '') {
                $brief[$field] = $patch[$field];
            }
        }

        if (isset($patch['genre']) && in_array($patch['genre'], Playbook::GENRES, true)) {
            $brief['genre'] = $patch['genre'];
        }

        if (isset($patch['page_ambition']) && is_int($patch['page_ambition']) && $patch['page_ambition'] > 0) {
            $brief['page_ambition'] = min($patch['page_ambition'], 200);
        }

        if (isset($patch['note']) && is_string($patch['note']) && $patch['note'] !== '') {
            $brief['notes'] = array_slice(array_merge($brief['notes'] ?? [], [$patch['note']]), -20);
        }

        return $brief;
    }

    private function recordUsage(StudioSession $session, string $phase, array $usage): void
    {
        $log = $session->token_usage ?? [];
        $log[] = [
            'phase' => $phase,
            'model' => $usage['model'] ?? '',
            'input' => $usage['input'] ?? 0,
            'output' => $usage['output'] ?? 0,
            'cache_write' => $usage['cache_write'] ?? 0,
            'cache_read' => $usage['cache_read'] ?? 0,
            'at' => now()->toIso8601String(),
        ];
        $session->token_usage = $log;

        $this->budget->record($session->tenant_id, $usage);
    }
}
