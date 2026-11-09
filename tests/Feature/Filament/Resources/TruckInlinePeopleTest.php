<?php

use App\Filament\Resources\Trucks\Pages\CreateTruck;
use App\Filament\Resources\Trucks\Pages\ListTrucks;
use App\Models\FleetPerson;
use App\Models\Truck;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('adds and selects a new person from the truck creation form', function (string $field, string $role) {
    Livewire::test(CreateTruck::class)->fillForm(['plate' => 'NUE-1'])
        ->callAction(TestAction::make('createOption')->schemaComponent($field, 'form'), ['name' => 'Persona nueva', 'phone' => '70000000'])
        ->assertHasNoActionErrors()->assertSet('data.'.$field, fn ($value): bool => filled($value))->assertSet('data.plate', 'NUE-1');

    $this->assertDatabaseHas('fleet_people', ['name' => 'Persona nueva', $role => true, 'phone' => '70000000']);
    $this->assertDatabaseCount('trucks', 0);
})->with([['owner_id', 'is_owner'], ['driver_id', 'is_driver']]);

test('warns about a normalized duplicate name without creating another person', function () {
    FleetPerson::factory()->create(['name' => 'Ana Pérez', 'document_number' => null]);

    Livewire::test(CreateTruck::class)->callAction(TestAction::make('createOption')->schemaComponent('owner_id', 'form'), ['name' => ' ana   pérez '])
        ->assertHasActionErrors(['name']);

    $this->assertDatabaseCount('fleet_people', 1);
});

test('reuses an existing conductor as owner without duplicating or overwriting the person', function () {
    $person = FleetPerson::factory()->create(['is_owner' => false, 'is_driver' => true]);

    Livewire::test(CreateTruck::class)->callAction(TestAction::make('createOption')->schemaComponent('owner_id', 'form'), ['existing_person_id' => $person->id])
        ->assertHasNoActionErrors()->assertSet('data.owner_id', $person->id);

    expect($person->fresh()->is_owner)->toBeTrue();
    expect($person->fresh()->is_driver)->toBeTrue();
    $this->assertDatabaseCount('fleet_people', 1);
});

test('adds a conductor inside the truck edit modal and keeps the truck form open', function () {
    $truck = Truck::factory()->create(['plate' => 'MOD-1']);
    $component = Livewire::test(ListTrucks::class)->mountAction(TestAction::make('edit')->table($truck));

    $component->callAction(TestAction::make('createOption')->schemaComponent('driver_id', 'mountedActionSchema0'), ['name' => 'Nuevo conductor'])
        ->assertHasNoActionErrors()->assertSet('mountedActions.0.data.driver_id', fn ($value): bool => filled($value))
        ->callMountedAction()->assertHasNoActionErrors();

    expect($truck->fresh()->conductor_name)->toBe('Nuevo conductor');
    expect($truck->fresh()->driver_id)->not->toBeNull();
});

test('a repeated document is reported inside the quick registration window', function () {
    FleetPerson::factory()->create(['name' => 'Persona registrada', 'document_number' => 'DOC-123']);

    Livewire::test(CreateTruck::class)->callAction(TestAction::make('createOption')->schemaComponent('driver_id', 'form'), ['name' => 'Otro nombre', 'document_number' => 'doc-123'])
        ->assertHasActionErrors(['name']);

    $this->assertDatabaseCount('fleet_people', 1);
});
