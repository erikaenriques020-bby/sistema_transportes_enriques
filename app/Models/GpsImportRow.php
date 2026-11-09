<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fleet_import_id', 'row_number', 'plate', 'name', 'payload', 'truck_id', 'observed_at', 'latitude', 'longitude', 'status', 'reason'])]
class GpsImportRow extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float', 'payload' => 'array'];
    }
}
