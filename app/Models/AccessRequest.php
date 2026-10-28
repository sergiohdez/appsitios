<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessRequest extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id', 'site_id', 'requester_id', 'status', 'purpose',
        'requested_from', 'requested_until', 'form_data', 'decided_by', 'decided_at', 'decision_comment', 'form_version_id', 'organization_form_version_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
            'form_data' => 'array',
            'requested_from' => 'datetime',
            'requested_until' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function people(): HasMany { return $this->hasMany(AccessPerson::class); }
    public function attachments(): HasMany { return $this->hasMany(AccessRequestAttachment::class); }
    public function formVersion(): BelongsTo { return $this->belongsTo(SiteFormVersion::class, 'form_version_id'); }
    public function organizationFormVersion(): BelongsTo { return $this->belongsTo(OrganizationFormVersion::class, 'organization_form_version_id'); }
}
