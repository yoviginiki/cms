<?php

namespace App\Models\IssueStudio;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudioSession extends Model
{
    use HasUuids;

    protected $table = 'issue_studio_sessions';

    protected $fillable = [
        'tenant_id', 'site_id', 'user_id', 'title', 'status',
        'brief', 'transcript', 'flatplan', 'magazine_issue_id', 'token_usage',
    ];

    protected $casts = [
        'brief' => 'array',
        'transcript' => 'array',
        'flatplan' => 'array',
        'token_usage' => 'array',
    ];

    public const STATUSES = ['interviewing', 'flatplanning', 'generating', 'complete', 'abandoned'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spreads(): HasMany
    {
        return $this->hasMany(StudioSpread::class, 'session_id')->orderBy('position');
    }

    public function magazineIssue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class, 'magazine_issue_id');
    }

    /** Sum of all logged token spend for this session. */
    public function totalTokens(): int
    {
        return array_sum(array_map(
            fn (array $u) => ($u['input'] ?? 0) + ($u['output'] ?? 0),
            $this->token_usage ?? []
        ));
    }
}
