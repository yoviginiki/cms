<?php

namespace App\Domain\Magazine\Models;

use App\Domain\IssueComposer\Models\MagazineIssue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MagazineDtpPage extends Model
{
    use HasUuids;

    protected $table = 'magazine_dtp_pages';

    protected $fillable = [
        'issue_id', 'spread_id', 'page_index', 'side',
        'width', 'height', 'bleed', 'margins', 'safe_area',
        'background', 'master_page_id', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'page_index' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'bleed' => 'array',
            'margins' => 'array',
            'safe_area' => 'array',
            'background' => 'array',
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

    public function frames(): HasMany
    {
        return $this->hasMany(MagazineFrame::class, 'page_id')->orderBy('z_index');
    }

    public function layers(): HasMany
    {
        return $this->hasMany(MagazineLayer::class, 'page_id')->orderBy('layer_order');
    }
}
