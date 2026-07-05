<?php

namespace App\Models\IssueStudio;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioSpread extends Model
{
    use HasUuids;

    protected $table = 'issue_studio_spreads';

    protected $fillable = [
        'tenant_id', 'session_id', 'position', 'status',
        'working_title', 'section', 'pattern', 'materials', 'intent',
        'page_ids', 'generation_notes',
    ];

    protected $casts = [
        'materials' => 'array',
        'page_ids' => 'array',
        'generation_notes' => 'array',
        'position' => 'integer',
    ];

    public const STATUSES = ['pending', 'generated', 'approved', 'revising'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StudioSession::class, 'session_id');
    }
}
