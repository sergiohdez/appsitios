<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientBulkImport extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'entity',
        'status',
        'errors_path',
        'total_records',
        'processed_records',
        'successful_records',
        'failed_records',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'total_records' => 'integer',
            'processed_records' => 'integer',
            'successful_records' => 'integer',
            'failed_records' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(ClientBulkImportRecord::class);
    }
}
