<?php

namespace App\Domain\Magazine\Models;

use App\Domain\IssueComposer\Models\MagazineIssue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MagazineSpread extends Model
{
    use HasUuids;

    protected $table = 'magazine_spreads';

    protected $fillable = [
        'issue_id', 'spread_index', 'name', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'spread_index' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class, 'issue_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(MagazineDtpPage::class, 'spread_id')->orderBy('page_index');
    }

    public function frames(): HasMany
    {
        return $this->hasMany(MagazineFrame::class, 'spread_id')->orderBy('z_index');
    }
}
