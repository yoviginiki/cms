<?php

namespace App\Domain\Magazine\Models;

use App\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MagStyle extends Model
{
    use HasUuids;

    protected $table = 'mag_styles';

    protected $fillable = [
        'site_id', 'name', 'type', 'properties',
        'based_on', 'next_style', 'sort_order', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on');
    }

    public function nextStyle(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_style');
    }

    /**
     * Walk the inheritance chain and merge properties.
     * Child properties override parent properties.
     */
    public function resolvedProperties(): array
    {
        $props = $this->properties ?? [];

        if ($this->based_on) {
            $parent = self::find($this->based_on);
            if ($parent) {
                $parentProps = $parent->resolvedProperties();
                $props = array_merge($parentProps, $props);
            }
        }

        return $props;
    }
}
