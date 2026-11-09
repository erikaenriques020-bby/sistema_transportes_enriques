<?php

use App\Filament\Resources\Trucks\Pages\EditTruck;
use App\Filament\Resources\Trucks\Pages\ListTrucks;
use App\Models\Truck;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('filters active and inactive trucks without deleting either record', function () {
    $active = Truck::factory()->create(['is_active' => true]);
    $inactive = Truck::factory()->create(['is_active' => false]);

    Livewire::test(ListTrucks::class)->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$inactive]);
    Livewire::test(ListTrucks::class)->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactive])->assertCanNotSeeTableRecords([$active]);

    $this->assertDatabaseCount('trucks', 2);
});

test('combines status and missing conductor filters correctly', function () {
    $activeWithout = Truck::factory()->create(['is_active' => true, 'conductor_name' => null]);
    $inactiveWithout = Truck::factory()->create(['is_active' => false, 'conductor_name' => null]);
    $activeWith = Truck::factory()->create(['is_active' => true, 'conductor_name' => 'Ana']);

    Livewire::test(ListTrucks::class)->filterTable('is_active', true)->filterTable('has_conductor', false)
        ->assertCanSeeTableRecords([$activeWithout])->assertCanNotSeeTableRecords([$inactiveWithout, $activeWith]);
    Livewire::test(ListTrucks::class)->filterTable('has_conductor', true)
        ->assertCanSeeTableRecords([$activeWith])->assertCanNotSeeTableRecords([$activeWithout, $inactiveWithout]);
});

test('can inactivate a truck from the form while preserving its plate and conductor', function () {
    $truck = Truck::factory()->create(['is_active' => true, 'conductor_name' => 'Ana']);

    Livewire::test(EditTruck::class, ['record' => $truck->getRouteKey()])
        ->fillForm(['plate' => $truck->plate, 'conductor_name' => 'Ana', 'is_active' => false])
        ->call('save')->assertHasNoFormErrors();

    $this->assertDatabaseHas('trucks', ['id' => $truck->id, 'plate' => $truck->plate, 'conductor_name' => 'Ana', 'is_active' => false]);
    $this->assertDatabaseCount('trucks', 1);
});
