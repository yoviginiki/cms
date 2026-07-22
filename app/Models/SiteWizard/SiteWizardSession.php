<?php

namespace App\Models\SiteWizard;

use App\Models\Menu;
use App\Models\Site;
use App\Models\Theme;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Site Wizard session: one whole-site build from a crawled URL or an
 * uploaded design ZIP. `steps` is the resumable pipeline checklist; `sources`
 * the per-page work list. Sibling of PageWizard\PageWizardSession but
 * tenant-level — the wizard creates the Site itself.
 */
class SiteWizardSession extends Model
{
    use HasUuids;

    protected $table = 'site_wizard_sessions';

    public const STATUSES = ['running', 'failed', 'review', 'accepted', 'abandoned'];
    public const SOURCES = ['url', 'zip'];

    /** Pipeline step keys in execution order. */
    public const STEP_KEYS = ['ingest', 'create_site', 'theme', 'polish', 'pages', 'menu', 'finalize'];

    public const STEP_LABELS = [
        'ingest' => 'Reading the design',
        'create_site' => 'Creating the site',
        'theme' => 'Building the theme',
        'polish' => 'AI theme polish',
        'pages' => 'Building the pages',
        'menu' => 'Building the navigation',
        'finalize' => 'Finishing up',
    ];

    protected $fillable = [
        'tenant_id', 'user_id', 'site_id', 'title', 'status', 'source',
        'reference_url', 'workspace_path', 'options', 'steps', 'sources',
        'style_signals', 'profile', 'nav', 'asset_map', 'theme_id', 'menu_id',
        'page_ids', 'token_usage', 'error',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'steps' => 'array',
            'sources' => 'array',
            'style_signals' => 'array',
            'profile' => 'array',
            'nav' => 'array',
            'asset_map' => 'array',
            'page_ids' => 'array',
            'token_usage' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** Fresh step checklist, every step pending. */
    public static function seedSteps(): array
    {
        return array_map(fn (string $key) => [
            'key' => $key,
            'label' => self::STEP_LABELS[$key],
            'status' => 'pending', // pending|running|done|failed|skipped
            'detail' => null,
            'at' => null,
        ], self::STEP_KEYS);
    }

    public function stepState(string $key): ?array
    {
        foreach ($this->steps ?? [] as $step) {
            if (($step['key'] ?? null) === $key) {
                return $step;
            }
        }

        return null;
    }

    public function markStep(string $key, string $status, ?string $detail = null): void
    {
        $steps = $this->steps ?? [];
        foreach ($steps as $i => $step) {
            if (($step['key'] ?? null) === $key) {
                $steps[$i]['status'] = $status;
                $steps[$i]['detail'] = $detail;
                $steps[$i]['at'] = now()->toIso8601String();
            }
        }
        $this->update(['steps' => $steps]);
    }

    /** First step that still needs work ('running' = an interrupted/batched step). */
    public function nextPendingStep(): ?string
    {
        foreach ($this->steps ?? [] as $step) {
            if (in_array($step['status'] ?? '', ['pending', 'running'], true)) {
                return $step['key'];
            }
        }

        return null;
    }

    public function updateSource(string $ref, array $patch): void
    {
        $sources = $this->sources ?? [];
        foreach ($sources as $i => $source) {
            if (($source['ref'] ?? null) === $ref) {
                $sources[$i] = array_merge($source, $patch);
            }
        }
        $this->update(['sources' => $sources]);
    }

    public function totalTokens(): int
    {
        return collect($this->token_usage ?? [])->sum(fn ($u) => (int) ($u['input'] ?? 0) + (int) ($u['output'] ?? 0));
    }
}
