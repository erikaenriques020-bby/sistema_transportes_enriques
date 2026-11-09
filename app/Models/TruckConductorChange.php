<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['truck_id', 'old_driver_id', 'new_driver_id', 'old_name', 'new_name', 'observed_at', 'fleet_import_id'])]
class TruckConductorChange extends Model
{
    protected function casts(): array
    {
        return ['observed_at' => 'datetime'];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
