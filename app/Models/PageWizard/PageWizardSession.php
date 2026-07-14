<?php

namespace App\Models\PageWizard;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Page Wizard session (sibling of ThemeWizard\WizardSession): source +
 * running transcript + current block-manifest + the real draft page it builds.
 */
class PageWizardSession extends Model
{
    use HasUuids;

    protected $table = 'page_wizard_sessions';

    public const STATUSES = ['capturing', 'capture_failed', 'drafting', 'accepted', 'abandoned'];
    public const MODES = ['layout', 'content', 'describe'];

    protected $fillable = [
        'tenant_id', 'site_id', 'user_id', 'title', 'status', 'source', 'mode',
        'reference_url', 'transcript', 'manifest', 'page_id', 'token_usage', 'error',
    ];

    protected function casts(): array
    {
        return [
            'transcript' => 'array',
            'manifest' => 'array',
            'token_usage' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function totalTokens(): int
    {
        return collect($this->token_usage ?? [])->sum(fn ($u) => (int) ($u['input'] ?? 0) + (int) ($u['output'] ?? 0));
    }
}
