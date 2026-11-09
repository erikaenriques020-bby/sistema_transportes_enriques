<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[Fillable(['plate', 'conductor_name', 'gps_asset_identifier', 'make', 'model', 'owner_id', 'driver_id', 'driver_observed_at', 'driver_recorded_at', 'driver_source_import_id', 'is_active', 'notes'])]
class Truck extends Model
{
    use HasFactory;

    public static function normalizePlate(string $plate): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($plate)) ?? '');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'driver_observed_at' => 'datetime', 'driver_recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Truck $truck): void {
            $truck->plate = self::normalizePlate($truck->plate);
            if ($truck->isDirty('owner_id') && $truck->owner_id && ! FleetPerson::owners()->whereKey($truck->owner_id)->exists()) {
                throw ValidationException::withMessages(['owner_id' => 'Selecciona una persona con rol de dueño.']);
            }
            if ($truck->isDirty('driver_id') && ($truck->driver_id !== null || $truck->exists)) {
                $driver = $truck->driver_id ? FleetPerson::drivers()->find($truck->driver_id) : null;
                if ($truck->driver_id && ! $driver) {
                    throw ValidationException::withMessages(['driver_id' => 'Selecciona una persona con rol de conductor.']);
                }
                if ($driver || ! $truck->isDirty('conductor_name')) {
                    $truck->conductor_name = $driver?->name;
                }
            } elseif ($truck->isDirty('conductor_name') && $truck->driver_id && self::normalizePlate((string) $truck->conductor_name) !== self::normalizePlate((string) $truck->driver?->name)) {
                $truck->driver_id = null;
            }
            if ($truck->isDirty('conductor_name')) {
                $truck->conductor_name = filled($truck->conductor_name) ? preg_replace('/\s+/u', ' ', trim($truck->conductor_name)) : null;
                $truck->driver_recorded_at = now();
                if (! $truck->isDirty('driver_source_import_id')) {
                    $truck->driver_source_import_id = null;
                    $truck->driver_observed_at = null;
                }
            }
        });
        static::created(function (Truck $truck): void {
            if (filled($truck->conductor_name)) {
                $truck->conductorChanges()->create(['new_name' => $truck->conductor_name, 'new_driver_id' => $truck->driver_id, 'observed_at' => $truck->driver_observed_at, 'fleet_import_id' => $truck->driver_source_import_id]);
            }
            if ($truck->driver_id !== null) {
                $truck->driverAssignments()->create(['driver_id' => $truck->driver_id, 'assigned_at' => now()]);
            }
        });

        static::updated(function (Truck $truck): void {
            if ($truck->wasChanged('conductor_name') || $truck->wasChanged('driver_id')) {
                $truck->conductorChanges()->create(['old_name' => $truck->getOriginal('conductor_name'), 'new_name' => $truck->conductor_name, 'old_driver_id' => $truck->getOriginal('driver_id'), 'new_driver_id' => $truck->driver_id, 'observed_at' => $truck->driver_observed_at, 'fleet_import_id' => $truck->driver_source_import_id]);
            }
            if (! $truck->wasChanged('driver_id')) {
                return;
            }

            $truck->driverAssignments()->whereNull('ended_at')->latest('assigned_at')->first()?->update(['ended_at' => now()]);

            if ($truck->driver_id !== null) {
                $truck->driverAssignments()->create(['driver_id' => $truck->driver_id, 'assigned_at' => now()]);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(FleetPerson::class, 'owner_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(FleetPerson::class, 'driver_id');
    }

    public function driverAssignments(): HasMany
    {
        return $this->hasMany(TruckDriverAssignment::class);
    }

    public function conductorChanges(): HasMany
    {
        return $this->hasMany(TruckConductorChange::class);
    }

    public function gpsPositions(): HasMany
    {
        return $this->hasMany(GpsPosition::class);
    }

    public function latestGpsPosition(): HasOne
    {
        return $this->hasOne(GpsPosition::class)->ofMany(['observed_at' => 'max', 'id' => 'max'], fn (Builder $query): Builder => $query->validCoordinates());
    }
}
