<?php

namespace App\Models\Magazine;

use App\Domain\IssueComposer\Models\MagazineIssue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MagArticle extends Model
{
    use HasUuids;

    protected $table = 'mag_articles';

    protected $fillable = [
        'issue_id', 'slug', 'title', 'page_count', 'rhythm',
        'role', 'wizard_plan', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'sort_order' => 'integer',
            'wizard_plan' => 'array', // PHASE_12_PORT: jsonb -> json for MySQL
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class, 'issue_id');
    }
}
