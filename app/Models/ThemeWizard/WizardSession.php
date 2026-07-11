<?php

namespace App\Models\ThemeWizard;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WizardSession extends Model
{
    use HasUuids;

    protected $table = 'theme_wizard_sessions';

    protected $fillable = [
        'tenant_id', 'site_id', 'user_id', 'title', 'status', 'source',
        'reference_url', 'transcript', 'profile', 'candidate', 'theme_id', 'token_usage', 'error',
    ];

    protected $casts = [
        'transcript' => 'array',
        'profile' => 'array',
        'candidate' => 'array',
        'token_usage' => 'array',
    ];

    // capturing / capture_failed cover the async "from URL" path, where the
    // Playwright screenshot runs on the queue worker (proc_open is disabled in
    // the web pool). Upload/conversation start straight in `drafting`.
    public const STATUSES = ['capturing', 'capture_failed', 'drafting', 'accepted', 'abandoned'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Total input+output tokens spent across the conversation. */
    public function totalTokens(): int
    {
        $t = 0;
        foreach ($this->token_usage ?? [] as $u) {
            $t += (int) ($u['input'] ?? 0) + (int) ($u['output'] ?? 0);
        }
        return $t;
    }
}
