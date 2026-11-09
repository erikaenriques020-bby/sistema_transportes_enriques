<?php

use App\Filament\Resources\FleetPeople\FleetPersonResource;
use App\Filament\Resources\FleetPeople\Pages\CreateFleetPerson;
use App\Filament\Resources\FleetPeople\Pages\ListFleetPeople;
use App\Filament\Resources\Owners\OwnerResource;
use App\Filament\Resources\Owners\Pages\CreateOwner;
use App\Filament\Resources\Owners\Pages\ListOwners;
use App\Filament\Resources\Trucks\Pages\CreateTruck;
use App\Filament\Resources\Trucks\Pages\ListTrucks;
use App\Filament\Resources\Trucks\TruckResource;
use App\Models\FleetPerson;
use App\Models\Truck;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('edits a truck in a modal without redirecting and records a manual conductor change', function () {
    $truck = Truck::factory()->create(['plate' => 'MOD-1', 'conductor_name' => 'Anterior']);
    $action = TestAction::make('edit')->table($truck);

    Livewire::test(ListTrucks::class)->assertActionExists($action, fn (Action $edit): bool => $edit->getUrl() === null)->mountAction($action)->assertActionMounted($action)
        ->fillForm(['conductor_name' => 'Actual'])->callMountedAction()->assertHasNoActionErrors()->assertNoRedirect();

    expect($truck->fresh()->conductor_name)->toBe('Actual');
    expect($truck->fresh()->driver_recorded_at)->not->toBeNull();
    expect($truck->conductorChanges()->latest('id')->first()->old_name)->toBe('Anterior');
});

test('edits owners and conductors in floating windows', function (string $page, bool $owner) {
    $person = FleetPerson::factory()->create(['is_owner' => $owner, 'is_driver' => ! $owner]);
    $action = TestAction::make('edit')->table($person);

    Livewire::test($page)->assertActionExists($action, fn (Action $edit): bool => $edit->getUrl() === null)->mountAction($action)->assertActionMounted($action)
        ->fillForm(['phone' => '70000000'])->callMountedAction()->assertHasNoActionErrors()->assertNoRedirect();

    expect($person->fresh()->phone)->toBe('70000000');
})->with([[ListFleetPeople::class, false], [ListOwners::class, true]]);

test('manual creation returns to the corresponding list', function (string $page, string $resource, array $data) {
    Livewire::test($page)->fillForm($data)->call('create')->assertHasNoFormErrors()->assertRedirect($resource::getUrl('index'));
})->with([
    [CreateTruck::class, TruckResource::class, ['plate' => 'REG-1']],
    [CreateFleetPerson::class, FleetPersonResource::class, ['name' => 'Conductor prueba', 'is_driver' => true, 'is_owner' => false]],
    [CreateOwner::class, OwnerResource::class, ['name' => 'Dueño prueba', 'is_driver' => false, 'is_owner' => true]],
]);

test('shows GPS date and time separately from the system change timestamp', function () {
    $truck = Truck::factory()->create(['conductor_name' => 'Ana']);
    $truck->update(['driver_observed_at' => '2026-10-26 15:34:22']);

    Livewire::test(ListTrucks::class)->assertSee('Fecha GPS del conductor')->assertSee('Hora GPS')->assertSee('26/10/2026')->assertSee('15:34:22')->assertSee('Último cambio en el sistema');
});
