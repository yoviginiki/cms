<?php

namespace App\Domain\Magazine\Models;

use App\Domain\IssueComposer\Models\MagazineIssue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MagazineLayer extends Model
{
    use HasUuids;

    protected $table = 'magazine_layers';

    protected $fillable = [
        'issue_id', 'page_id', 'name', 'layer_order',
        'visible', 'locked', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'layer_order' => 'integer',
            'visible' => 'boolean',
            'locked' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class, 'issue_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MagazineDtpPage::class, 'page_id');
    }

    public function frames(): HasMany
    {
        return $this->hasMany(MagazineFrame::class, 'layer_id')->orderBy('z_index');
    }
}
