<?php

namespace App\Models;

use App\Services\LocationImportSchema;
use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'name',
    'place_group',
    'icon',
    'latitude',
    'longitude',
    'phone',
    'email',
    'description',
    'address',
])]
class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory;

    public static function identityKey(string $name, float|string $latitude, float|string $longitude): string
    {
        return hash('sha256', Truck::normalizePlate($name).'|'.LocationImportSchema::coordinate((string) $latitude, 90).'|'.LocationImportSchema::coordinate((string) $longitude, 180));
    }

    protected static function booted(): void
    {
        static::saving(function (Place $place): void {
            $place->name = preg_replace('/\s+/u', ' ', trim($place->name));
            Validator::make($place->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'email' => ['nullable', 'email', 'max:255'],
            ])->validate();
            $place->import_key = self::identityKey($place->name, $place->latitude, $place->longitude);
            if (self::where('import_key', $place->import_key)->whereKeyNot($place->getKey() ?? 0)->exists()) {
                throw ValidationException::withMessages(['name' => 'Ya existe este lugar con el mismo nombre y coordenadas.']);
            }
        });
    }
}
