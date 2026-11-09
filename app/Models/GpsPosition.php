<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

#[Fillable(['truck_id', 'observed_at', 'latitude', 'longitude', 'fleet_import_id', 'source_row', 'details', 'source_metadata'])]
class GpsPosition extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float', 'details' => 'array', 'source_metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (GpsPosition $position): void {
            Validator::make($position->getAttributes(), [
                'truck_id' => ['required', 'integer', 'exists:trucks,id'],
                'observed_at' => ['required', 'date'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'fleet_import_id' => ['nullable', 'integer', 'exists:fleet_imports,id'],
                'source_row' => ['nullable', 'integer', 'min:1'],
            ])->validate();
        });
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function fleetImport(): BelongsTo
    {
        return $this->belongsTo(FleetImport::class);
    }

    public function scopeValidCoordinates(Builder $query): Builder
    {
        return $query->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])->whereNotNull('observed_at');
    }
}
