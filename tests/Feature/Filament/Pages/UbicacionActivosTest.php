<?php

use App\Filament\Pages\UbicacionActivos;
use App\Models\GpsPosition;
use App\Models\Place;
use App\Models\Truck;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['services.google_maps.browser_key' => 'test-browser-key']);
});

test('shows actual places and trucks without inventing truck locations', function () {
    Truck::factory()->create(['plate' => 'SIN-GPS']);
    Place::factory()->create(['name' => 'Lugar real', 'latitude' => -16.5, 'longitude' => -68.15]);

    Livewire::test(UbicacionActivos::class)->assertSee('SIN-GPS')->assertSee('Todavía no hay posiciones GPS importadas')->assertSee('Lugares de referencia');

    $this->assertDatabaseCount('gps_positions', 0);
});

test('selects the latest valid GPS observation instead of the latest inserted row', function () {
    $truck = Truck::factory()->create();
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-27 14:00:00', 'latitude' => -16.5, 'longitude' => -68.1]);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-26 14:00:00']);
    DB::table('gps_positions')->insert(['truck_id' => $truck->id, 'observed_at' => '2026-10-28 14:00:00', 'latitude' => 100, 'longitude' => -68]);

    Livewire::test(UbicacionActivos::class)->call('selectTruck', $truck->id)->assertSet('points.0.lat', -16.5)->assertSet('points.0.at', '27/10/2026 14:00:00')->assertSee('No es ubicación en tiempo real.');
});

test('queries one truck and day in chronological order without loading other trucks or dates', function () {
    $truck = Truck::factory()->create();
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-27 12:00:00']);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-27 09:00:00']);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-28 00:00:00']);
    GpsPosition::factory()->create(['observed_at' => '2026-10-27 10:00:00']);

    $component = Livewire::test(UbicacionActivos::class)->call('selectTruck', $truck->id)->set('date', '2026-10-27')->call('showRoute')
        ->assertHasNoErrors()->assertSet('points.0.at', '27/10/2026 09:00:00')->assertSet('points.1.at', '27/10/2026 12:00:00')->assertSee('2 posiciones registradas');
    expect($component->get('points'))->toHaveCount(2);
});

test('clears the previous truck route on an empty selection or date', function () {
    $position = GpsPosition::factory()->create(['observed_at' => '2026-10-27 09:00:00']);
    $missing = Truck::factory()->create();
    $component = Livewire::test(UbicacionActivos::class)->call('selectTruck', $position->truck_id)->call('selectTruck', $missing->id)
        ->assertSet('points', [])->assertSee('Este camión no tiene posiciones GPS válidas importadas.');
    $component->set('date', '2026-10-27')->call('showRoute')->assertSet('points', [])->assertSee('No hay posiciones GPS válidas para la fecha seleccionada.');
});

test('validates coordinate ranges and route date', function () {
    expect(fn () => GpsPosition::factory()->create(['latitude' => 91]))->toThrow(ValidationException::class);
    Livewire::test(UbicacionActivos::class)->set('date', 'invalid')->call('showRoute')->assertHasErrors(['date', 'selectedTruckId']);
});

test('requires the existing panel authentication before rendering the maps key or GPS data', function () {
    $this->get('/admin/ubicacion/activos')->assertRedirect('/admin/login')->assertDontSee('test-browser-key');
});

test('selects the last GPS date so reports from yesterday are shown without changing the calendar', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 12:00:00'));
    $truck = Truck::factory()->create();
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-26 08:00:00']);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-26 18:00:00']);

    Livewire::test(UbicacionActivos::class)->call('selectTruck', $truck->id)->assertSet('date', '2026-10-26')
        ->call('showRoute')->assertHasNoErrors()->assertSet('routeSummary.total', 2)->assertSet('routeSummary.displayed', 2)
        ->assertSet('routeSummary.from', '26/10/2026 08:00:00')->assertSet('routeSummary.to', '26/10/2026 18:00:00');
});

test('replaces the previous date and route summary when selecting a different truck', function () {
    $first = GpsPosition::factory()->create(['observed_at' => '2026-10-25 09:00:00']);
    $second = GpsPosition::factory()->create(['observed_at' => '2026-10-26 10:00:00']);

    Livewire::test(UbicacionActivos::class)->call('selectTruck', $first->truck_id)->call('showRoute')->assertSet('routeSummary.total', 1)
        ->call('selectTruck', $second->truck_id)->assertSet('routeSummary', null)->assertSet('date', '2026-10-26')
        ->call('showRoute')->assertSet('routeSummary.total', 1)->set('date', '2026-10-27')->call('showRoute')
        ->assertSet('points', [])->assertSet('routeSummary', null)->assertSee('No hay posiciones GPS válidas para la fecha seleccionada.');
});

test('explains coincident observations instead of suggesting that GPS points are missing', function () {
    $truck = Truck::factory()->create();
    foreach (['08:00:00', '18:00:00'] as $time) {
        GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-26 '.$time, 'latitude' => -16.5, 'longitude' => -68.1]);
    }

    Livewire::test(UbicacionActivos::class)->call('selectTruck', $truck->id)->call('showRoute')
        ->assertSet('routeSummary.total', 2)->assertSet('routeSummary.distinct_locations', 1)
        ->assertSee('Todas las observaciones tienen las mismas coordenadas.');
});

test('shows fleet overview with latest positions and excludes trucks without GPS markers', function () {
    $truck = Truck::factory()->create();
    Truck::factory()->create();
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-26 08:00:00']);
    GpsPosition::factory()->create(['truck_id' => $truck->id, 'observed_at' => '2026-10-26 18:00:00']);

    Livewire::test(UbicacionActivos::class)->assertViewHas('fleetPositions', fn ($fleet): bool => count($fleet) === 1 && $fleet[0]['point']['at'] === '26/10/2026 18:00:00')
        ->call('selectTruck', $truck->id)->call('showRoute')->call('showFleet')
        ->assertSet('selection', null)->assertSet('selectedTruckId', null)->assertSet('points', [])->assertSet('routeSummary', null)
        ->assertDispatched('fleet-map-data');
});

test('filters the compact truck list by GPS availability and administrative activity', function () {
    $located = Truck::factory()->create(['is_active' => true]);
    $missing = Truck::factory()->create(['is_active' => false]);
    GpsPosition::factory()->create(['truck_id' => $located->id]);

    Livewire::test(UbicacionActivos::class)->set('positionFilter', 'located')
        ->assertViewHas('trucks', fn ($trucks): bool => $trucks->pluck('id')->all() === [$located->id])
        ->set('positionFilter', 'missing')->assertViewHas('trucks', fn ($trucks): bool => $trucks->pluck('id')->all() === [$missing->id])
        ->set('positionFilter', 'inactive')->assertViewHas('trucks', fn ($trucks): bool => $trucks->pluck('id')->all() === [$missing->id]);
});
