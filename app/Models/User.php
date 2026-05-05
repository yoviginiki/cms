<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'tenant_id', 'role',
        'last_login_at', 'invitation_token', 'invitation_expires_at', 'invited_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin']);
    }

    public function isEditor(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'editor']);
    }

    public function hasMinimumRole(string $role): bool
    {
        $hierarchy = ['viewer' => 0, 'author' => 1, 'editor' => 2, 'admin' => 3, 'owner' => 4];
        $userLevel = $hierarchy[$this->role] ?? 0;
        $requiredLevel = $hierarchy[$role] ?? 0;

        return $userLevel >= $requiredLevel;
    }
}
