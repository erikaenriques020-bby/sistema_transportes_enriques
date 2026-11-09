<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'document_number', 'phone', 'is_owner', 'is_driver', 'owner_id', 'is_active'])]
class FleetPerson extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (FleetPerson $person): void {
            if ($reason = $person->deletionBlockReason()) {
                throw ValidationException::withMessages(['person' => $reason.' Puedes inactivar el registro para conservar sus relaciones.']);
            }
        });
        static::saving(function (FleetPerson $person): void {
            $person->name = preg_replace('/\s+/u', ' ', trim($person->name));
            $person->document_number = filled($person->document_number) ? mb_strtoupper(trim($person->document_number)) : null;
            if ($person->isDirty('name') || $person->isDirty('document_number')) {
                $duplicate = self::whereKeyNot($person->getKey() ?? 0)->get(['id', 'name', 'document_number'])->contains(fn (FleetPerson $other): bool => ($person->document_number && mb_strtoupper((string) $other->document_number) === $person->document_number)
                    || (Truck::normalizePlate($other->name) === Truck::normalizePlate($person->name) && (! $person->document_number || ! $other->document_number)));
                if ($duplicate) {
                    throw ValidationException::withMessages(['name' => 'Ya existe una ficha con este nombre o documento. Reutiliza esa persona; para homónimos registra documentos distintos.']);
                }
            }
            if (! $person->is_owner && ! $person->is_driver) {
                throw ValidationException::withMessages(['is_driver' => 'Selecciona al menos un rol.']);
            }
            if (! $person->is_owner && $person->trucks()->exists()) {
                throw ValidationException::withMessages(['is_owner' => 'Esta persona tiene camiones propios; reasígnalos antes de quitar el rol.']);
            }
            if (! $person->is_driver && $person->drivenTrucks()->exists()) {
                throw ValidationException::withMessages(['is_driver' => 'Esta persona conduce camiones; reasígnalos antes de quitar el rol.']);
            }
        });
        static::updated(function (FleetPerson $person): void {
            if ($person->wasChanged('name')) {
                $person->drivenTrucks()->get()->each(fn (Truck $truck): bool => $truck->update(['conductor_name' => $person->name]));
            }
        });
    }

    protected function casts(): array
    {
        return ['is_owner' => 'boolean', 'is_driver' => 'boolean', 'is_active' => 'boolean'];
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->trucks()->exists() || $this->drivenTrucks()->exists()) {
            return 'Esta persona tiene camiones asociados como dueño o conductor y no se puede eliminar.';
        }
        if (TruckDriverAssignment::where('driver_id', $this->id)->exists()
            || TruckConductorChange::where(fn (Builder $query): Builder => $query->where('old_driver_id', $this->id)->orWhere('new_driver_id', $this->id))->exists()) {
            return 'Esta persona tiene historial de conductores asociado y no se puede eliminar.';
        }
        if ($this->owner_id !== null || self::where('owner_id', $this->id)->exists()) {
            return 'Esta persona tiene relaciones con otras personas de la flota y no se puede eliminar.';
        }
        $name = Truck::normalizePlate($this->name);
        if (Truck::whereNotNull('conductor_name')->pluck('conductor_name')->contains(fn (string $value): bool => Truck::normalizePlate($value) === $name)
            || TruckConductorChange::get(['old_name', 'new_name'])->contains(fn (TruckConductorChange $change): bool => Truck::normalizePlate((string) $change->old_name) === $name || Truck::normalizePlate((string) $change->new_name) === $name)) {
            return 'Su nombre aparece en camiones o en el historial GPS. Por precaución no se elimina; la coincidencia de nombre no confirma identidad.';
        }

        return null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'owner_id');
    }

    public function affiliatedDrivers(): HasMany
    {
        return $this->hasMany(self::class, 'owner_id')->where('is_driver', true);
    }

    public function trucks(): HasMany
    {
        return $this->hasMany(Truck::class, 'owner_id');
    }

    public function drivenTrucks(): HasMany
    {
        return $this->hasMany(Truck::class, 'driver_id');
    }

    public function scopeOwners(Builder $query): Builder
    {
        return $query->where('is_owner', true);
    }

    public function scopeDrivers(Builder $query): Builder
    {
        return $query->where('is_driver', true);
    }
}
