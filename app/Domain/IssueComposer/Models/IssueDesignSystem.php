<?php

namespace App\Domain\IssueComposer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueDesignSystem extends Model
{
    use HasUuids;

    protected $table = 'issue_design_system';

    protected $fillable = [
        'issue_id', 'palette', 'typography', 'grid', 'image_style', 'source_run_id',
    ];

    protected function casts(): array
    {
        return [
            'palette' => 'array',
            'typography' => 'array',
            'grid' => 'array',
            'image_style' => 'array',
        ];
    }

    public function issue(): BelongsTo { return $this->belongsTo(MagazineIssue::class, 'issue_id'); }
    public function sourceRun(): BelongsTo { return $this->belongsTo(MagazineCurationRun::class, 'source_run_id'); }
}
