<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAdminAccount extends Model
{
    protected $table = 'client_admin_accounts';

    public $incrementing = false;

    protected $primaryKey = 'organization_id';

    protected $keyType = 'string';

    protected $fillable = ['organization_id', 'membership_id'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
