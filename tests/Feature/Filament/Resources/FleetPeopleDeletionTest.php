<?php

use App\Filament\Resources\FleetPeople\Pages\ListFleetPeople;
use App\Filament\Resources\Owners\Pages\ListOwners;
use App\Models\FleetPerson;
use App\Models\Truck;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('requires confirmation and allows deleting unrelated owners and conductors', function (string $page, bool $owner) {
    $person = FleetPerson::factory()->create(['is_owner' => $owner, 'is_driver' => ! $owner]);
    $action = TestAction::make('delete')->table($person);
    $component = Livewire::test($page)->mountAction($action)->assertActionMounted($action);
    $this->assertModelExists($person);

    $component->callMountedAction()->assertHasNoActionErrors()->assertNotified('Registro eliminado');

    $this->assertModelMissing($person);
})->with([[ListFleetPeople::class, false], [ListOwners::class, true]]);

test('canceling deletion preserves the person and both roles', function () {
    $person = FleetPerson::factory()->create(['is_owner' => true, 'is_driver' => true]);

    Livewire::test(ListOwners::class)->mountAction(TestAction::make('delete')->table($person))->unmountAction();

    expect($person->fresh()->is_owner)->toBeTrue();
    expect($person->fresh()->is_driver)->toBeTrue();
});

test('blocks deletion for trucks, historical links and historical GPS names even through the model', function (string $association) {
    $person = FleetPerson::factory()->create(['name' => 'Persona protegida', 'is_owner' => true]);
    $truck = Truck::factory()->create();
    match ($association) {
        'owner' => $truck->update(['owner_id' => $person->id]),
        'driver' => $truck->update(['driver_id' => $person->id]),
        'assignment' => $truck->driverAssignments()->create(['driver_id' => $person->id, 'assigned_at' => now()->subDay(), 'ended_at' => now()]),
        'old_driver' => $truck->conductorChanges()->create(['old_driver_id' => $person->id]),
        'new_driver' => $truck->conductorChanges()->create(['new_driver_id' => $person->id]),
        'gps_current' => $truck->update(['conductor_name' => ' persona   protegida ']),
        'gps_history' => $truck->conductorChanges()->create(['old_name' => 'PERSONA PROTEGIDA', 'new_name' => 'Otro nombre']),
        'affiliated' => FleetPerson::factory()->create(['owner_id' => $person->id]),
    };

    expect(fn () => $person->delete())->toThrow(ValidationException::class);

    $this->assertModelExists($person);
    $this->assertModelExists($truck);
})->with(['owner', 'driver', 'assignment', 'old_driver', 'new_driver', 'gps_current', 'gps_history', 'affiliated']);

test('offers inactivation instead of deletion and preserves all truck relationships and history', function (string $page) {
    $person = FleetPerson::factory()->create(['is_owner' => true, 'is_driver' => true]);
    $truck = Truck::factory()->create(['owner_id' => $person->id, 'driver_id' => $person->id]);
    $action = TestAction::make('delete')->table($person);
    $component = Livewire::test($page)->mountAction($action)->assertActionMounted($action);
    expect($component->instance()->getMountedAction()->getModalHeading())->toBe('No se puede eliminar este registro');
    expect($component->instance()->getMountedAction()->getModalSubmitAction())->toBeNull();

    $component->callMountedAction(['deactivate' => true])->assertHasNoActionErrors()->assertNotified('Registro inactivado; relaciones conservadas');

    expect($person->fresh()->is_active)->toBeFalse();
    expect($truck->fresh()->owner_id)->toBe($person->id);
    expect($truck->fresh()->driver_id)->toBe($person->id);
    expect($truck->conductorChanges()->count())->toBe(1);
    expect($truck->driverAssignments()->count())->toBe(1);
})->with([ListFleetPeople::class, ListOwners::class]);

test('rechecks newly added relationships after opening confirmation without silently inactivating', function () {
    $person = FleetPerson::factory()->create();
    $component = Livewire::test(ListFleetPeople::class)->mountAction(TestAction::make('delete')->table($person));
    $truck = Truck::factory()->create(['driver_id' => $person->id]);

    $component->callMountedAction()->assertNotified('No se puede eliminar');

    expect($person->fresh()->is_active)->toBeTrue();
    expect($truck->fresh()->driver_id)->toBe($person->id);
});
