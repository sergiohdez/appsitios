<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Membership extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['organization_id', 'user_id', 'role', 'is_active'];

    protected function casts(): array
    {
        return ['role' => MembershipRole::class, 'is_active' => 'boolean'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function clientAdminAccount(): HasOne
    {
        return $this->hasOne(ClientAdminAccount::class, 'membership_id');
    }

    public function canManage(): bool
    {
        return $this->role === MembershipRole::CLIENT_ADMIN && $this->clientAdminAccount()->exists();
    }
    public function canApprove(): bool { return $this->role === MembershipRole::APPROVER; }
    public function canRequest(): bool { return $this->role?->canRequest() ?? false; }

    public function approvableSites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_approvers')->withTimestamps();
    }

    public function createdAccessRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccessRequest::class, 'requester_id', 'user_id')
            ->whereColumn('access_requests.organization_id', 'memberships.organization_id');
    }
}
