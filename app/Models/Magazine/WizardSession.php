<?php

namespace App\Models\Magazine;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WizardSession extends Model
{
    use HasUuids;

    protected $table = 'mag_wizard_sessions';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'current_step',
        'status',
        'provisioned_issue_id',
        'step1_brief',
        'step2_structure',
        'step3_article_selection',
        'step4_analyses',
        'step5_directions',
        'step6_thumbnails',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'step1_brief' => 'array',
            'step2_structure' => 'array',
            'step3_article_selection' => 'array',
            'step4_analyses' => 'array',
            'step5_directions' => 'array',
            'step6_thumbnails' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WizardMessage::class, 'session_id')->orderBy('created_at');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
