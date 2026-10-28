<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name', 'legal_name', 'tax_id', 'billing_email', 'contact_name',
        'contact_email', 'timezone', 'locale', 'brand_config', 'is_active',
    ];

    protected function casts(): array
    {
        return ['brand_config' => 'array', 'is_active' => 'boolean'];
    }

    public function memberships(): HasMany { return $this->hasMany(Membership::class); }
    public function clientAdminAccount(): HasOne { return $this->hasOne(ClientAdminAccount::class); }
    public function sites(): HasMany { return $this->hasMany(Site::class); }
    public function accessRequests(): HasMany { return $this->hasMany(AccessRequest::class); }
    public function requestFormVersions(): HasMany { return $this->hasMany(OrganizationFormVersion::class); }
    public function currentRequestFormVersion(): HasOne { return $this->hasOne(OrganizationFormVersion::class)->ofMany('version', 'max'); }
}
