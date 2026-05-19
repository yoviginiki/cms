<?php

namespace App\Domain\Magazine\Models;

use App\Domain\IssueComposer\Models\MagazineIssue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MagazineAssetReference extends Model
{
    use HasUuids;

    protected $table = 'magazine_asset_references';

    protected $fillable = [
        'issue_id', 'frame_id', 'source_url', 'alt', 'caption', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class, 'issue_id');
    }

    public function frame(): BelongsTo
    {
        return $this->belongsTo(MagazineFrame::class, 'frame_id');
    }
}
