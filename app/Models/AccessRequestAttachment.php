<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRequestAttachment extends Model
{
    use HasUlids;

    protected $fillable = ['storage_path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'];

    public function accessRequest(): BelongsTo { return $this->belongsTo(AccessRequest::class); }
}
