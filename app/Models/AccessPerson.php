<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessPerson extends Model
{
    use HasUlids;

    protected $fillable = ['full_name', 'document_type', 'document_number', 'company'];

    protected function casts(): array
    {
        return ['document_number' => 'encrypted'];
    }

    public function accessRequest(): BelongsTo { return $this->belongsTo(AccessRequest::class); }
}
