<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBulkImportRecord extends Model
{
    protected $fillable = ['client_bulk_import_id', 'source_row', 'data', 'status', 'errors'];

    protected function casts(): array
    {
        return [
            'source_row' => 'integer',
            'data' => 'array',
            'errors' => 'array',
        ];
    }

    public function bulkImport(): BelongsTo
    {
        return $this->belongsTo(ClientBulkImport::class, 'client_bulk_import_id');
    }
}
