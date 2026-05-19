<?php

namespace App\Domain\Magazine\Models;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Enums\FrameType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MagazineFrame extends Model
{
    use HasUuids;

    protected $table = 'magazine_frames';

    protected $fillable = [
        'issue_id', 'spread_id', 'page_id', 'layer_id',
        'frame_type', 'name',
        'x', 'y', 'width', 'height', 'rotation',
        'z_index', 'visible', 'locked',
        'content', 'style', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'frame_type' => FrameType::class,
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'rotation' => 'float',
            'z_index' => 'integer',
            'visible' => 'boolean',
            'locked' => 'boolean',
            'content' => 'array',
            'style' => 'array',
            'metadata' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class, 'issue_id');
    }

    public function spread(): BelongsTo
    {
        return $this->belongsTo(MagazineSpread::class, 'spread_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MagazineDtpPage::class, 'page_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(MagazineLayer::class, 'layer_id');
    }

    public function assetReferences(): HasMany
    {
        return $this->hasMany(MagazineAssetReference::class, 'frame_id');
    }
}
