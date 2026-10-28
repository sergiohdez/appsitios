<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Site extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['organization_id', 'name', 'code', 'address', 'timezone', 'form_schema', 'is_active'];

    protected function casts(): array
    {
        return ['form_schema' => 'array', 'is_active' => 'boolean'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function accessRequests(): HasMany { return $this->hasMany(AccessRequest::class); }
    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'site_approvers', 'site_id', 'membership_id')->withTimestamps();
    }
}
