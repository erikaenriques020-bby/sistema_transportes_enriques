<?php

namespace App\Models;

use Database\Factories\FleetImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sha256', 'kind', 'original_name', 'source_path', 'status', 'user_id', 'preview', 'decisions', 'result', 'imported_at'])]
class FleetImport extends Model
{
    /** @use HasFactory<FleetImportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['preview' => 'array', 'decisions' => 'array', 'result' => 'array', 'imported_at' => 'datetime'];
    }
}
