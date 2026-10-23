<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PlatformAdminRecoveryCode extends Model
{
    use HasUlids;

    protected $table = 'platform_admin_recovery_codes';

    public $timestamps = false;

    protected $fillable = ['code_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
