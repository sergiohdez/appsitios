<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAdminAccount extends Model
{
    protected $table = 'platform_admin_accounts';
    protected $primaryKey = 'singleton_id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = ['singleton_id', 'user_id', 'mfa_required'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    protected function casts(): array
    {
        return ['mfa_required' => 'boolean'];
    }
}
