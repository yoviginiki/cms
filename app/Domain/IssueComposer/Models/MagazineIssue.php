<?php

namespace App\Domain\IssueComposer\Models;

use App\Models\Magazine\MagArticle;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MagazineIssue extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'magazine_issues';

    protected $fillable = [
        'tenant_id', 'site_id', 'title', 'subtitle', 'theme', 'intention',
        'tone_knobs', 'target_page_count', 'language', 'status',
        'linked_page_id', 'created_by', 'curation_final', 'layout_final',
        'wizard_brief',
    ];

    protected function casts(): array
    {
        return [
            'tone_knobs' => 'array',
            'curation_final' => 'array',
            'layout_final' => 'array',
            'wizard_brief' => 'array', // PHASE_12_PORT: jsonb -> json for MySQL
            'target_page_count' => 'integer',
        ];
    }

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function linkedPage(): BelongsTo { return $this->belongsTo(Page::class, 'linked_page_id'); }
    public function contentItems(): HasMany { return $this->hasMany(IssueContentItem::class, 'issue_id')->orderBy('position'); }
    public function curationRuns(): HasMany { return $this->hasMany(MagazineCurationRun::class, 'issue_id')->orderByDesc('created_at'); }
    public function designSystem(): HasOne { return $this->hasOne(IssueDesignSystem::class, 'issue_id'); }
    public function articles(): HasMany { return $this->hasMany(MagArticle::class, 'issue_id')->orderBy('sort_order'); }

    public function scopeForSite($query, string $siteId) { return $query->where('site_id', $siteId); }
    public function scopeByStatus($query, string $status) { return $query->where('status', $status); }
}
