<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function memberships(): HasMany { return $this->hasMany(Membership::class); }

    public function platformAdminAccount(): HasOne { return $this->hasOne(PlatformAdminAccount::class); }

    public function membershipFor(?string $organizationId): ?Membership
    {
        if (! $organizationId) {
            return null;
        }

        return $this->memberships()->where('organization_id', $organizationId)->where('is_active', true)->first();
    }
}
