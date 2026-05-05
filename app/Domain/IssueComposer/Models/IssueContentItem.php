<?php

namespace App\Domain\IssueComposer\Models;

use App\Models\Post;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueContentItem extends Model
{
    use HasUuids;

    protected $table = 'issue_content_items';

    protected $fillable = [
        'issue_id', 'source_type', 'source_id', 'extra_payload',
        'importance', 'role_hint', 'editor_note', 'ai_decision',
        'assigned_section_id', 'position',
    ];

    protected function casts(): array
    {
        return [
            'extra_payload' => 'array',
            'position' => 'integer',
        ];
    }

    public function issue(): BelongsTo { return $this->belongsTo(MagazineIssue::class, 'issue_id'); }

    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'source_id');
    }
}
