<?php

namespace App\Domain\Magazine\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MagPage extends Model
{
    use HasUuids;

    protected $table = 'mag_pages';

    protected $fillable = [
        'page_id', 'page_number', 'page_size', 'margins', 'bleed',
        'columns', 'baseline_grid', 'master_page_id', 'is_master',
        'spread_with', 'background_color', 'background_asset_id',
        'spread_role', 'spread_density', 'spread_tension',
    ];

    protected function casts(): array
    {
        return [
            'page_size' => 'array',
            'margins' => 'array',
            'bleed' => 'array',
            'columns' => 'array',
            'baseline_grid' => 'array',
            'is_master' => 'boolean',
            'page_number' => 'integer',
            'spread_with' => 'integer',
        ];
    }

    public function cmsPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(MagElement::class, 'page_id', 'page_id')
            ->where('page_number', $this->page_number)
            ->orderBy('z_index');
    }

    public function masterPage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'master_page_id');
    }

    public function childPages(): HasMany
    {
        return $this->hasMany(self::class, 'master_page_id');
    }

    public function isMaster(): bool
    {
        return $this->is_master;
    }

    public function getSpread(): array
    {
        $pages = [$this];
        if ($this->spread_with) {
            $facing = self::where('page_id', $this->page_id)
                ->where('page_number', $this->spread_with)
                ->first();
            if ($facing) $pages[] = $facing;
        }
        return $pages;
    }

    public function getColumnGrid(): array
    {
        return $this->columns ?? ['count' => 1, 'gutter' => 12];
    }

    public function getBaselineGrid(): array
    {
        return $this->baseline_grid ?? ['increment' => 14, 'start' => 36];
    }
}
