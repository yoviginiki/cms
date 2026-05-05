<?php

namespace App\Domain\IssueComposer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MagazineCurationRun extends Model
{
    use HasUuids;

    const UPDATED_AT = null; // append-only

    protected $table = 'magazine_curation_runs';

    protected $fillable = [
        'issue_id', 'phase', 'input_hash', 'claude_model',
        'claude_input_tokens', 'claude_output_tokens',
        'output_jsonb', 'prompt_version', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'output_jsonb' => 'array',
            'claude_input_tokens' => 'integer',
            'claude_output_tokens' => 'integer',
        ];
    }

    public function issue(): BelongsTo { return $this->belongsTo(MagazineIssue::class, 'issue_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
