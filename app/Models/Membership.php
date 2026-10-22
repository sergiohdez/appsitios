<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'user_id', 'can_manage', 'can_approve', 'can_request', 'is_active'];

    protected function casts(): array
    {
        return ['can_manage' => 'boolean', 'can_approve' => 'boolean', 'can_request' => 'boolean', 'is_active' => 'boolean'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function approvableSites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_approvers')->withTimestamps();
    }
}
