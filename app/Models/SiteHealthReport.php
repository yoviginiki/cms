<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One health scan of a site (broken_links | pagespeed | stale_refs), stored for
 * history by the Site Health Ledger.
 */
class SiteHealthReport extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['site_id', 'type', 'data', 'summary', 'created_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'summary' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
