<x-filament-panels::page>
    <div x-data="{ listOpen: true, detailsOpen: true }" class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">Flota <strong>{{ $truckCount }}</strong></span>
                <span class="rounded-lg bg-success-50 px-3 py-2 text-success-700 dark:bg-success-950 dark:text-success-300">Con posición <strong>{{ $locatedCount }}</strong></span>
                <span class="rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">Sin posición <strong>{{ $truckCount - $locatedCount }}</strong></span>
                <span class="rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-800">Lugares de referencia <strong>{{ count($places) }}</strong></span>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button size="sm" color="gray" icon="heroicon-o-bars-3" x-on:click="listOpen = !listOpen" x-bind:aria-expanded="listOpen">Lista</x-filament::button>
                <x-filament::button size="sm" color="gray" icon="heroicon-o-globe-americas" wire:click="showFleet">Ver toda la flota</x-filament::button>
                <x-filament::button size="sm" tag="a" :href="\App\Filament\Pages\ImportarPosicionesGps::getUrl()" icon="heroicon-o-arrow-up-tray">Importar Excel GPS</x-filament::button>
            </div>
        </div>
        <p class="text-xs text-gray-500">Últimas posiciones de archivos importados · No es ubicación en tiempo real. {{ config('services.google_maps.demo') ? 'Google Maps · Clave de demostración.' : '' }}</p>
        @if ($locatedCount === 0)<p class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-950 dark:text-warning-300">Todavía no hay posiciones GPS importadas. El mapa muestra lugares de referencia, no ubicaciones de camiones.</p>@endif
        <div class="fleet-console" x-bind:class="{ 'fleet-console-wide': !listOpen }">
            <section x-show="listOpen" class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-label="Lista de camiones">
                <div class="flex flex-col gap-2 border-b border-gray-200 p-3 dark:border-gray-700">
                    <div class="flex items-center justify-between"><h2 class="text-sm font-semibold">Camiones</h2><span class="text-xs text-gray-500">{{ $trucks->total() }} resultados</span></div>
                    <label for="map-truck-search" class="sr-only">Buscar placa o conductor</label><input id="map-truck-search" wire:model.live.debounce.400ms="search" placeholder="Buscar placa o conductor…" class="w-full rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-600 dark:bg-gray-950">
                    <label class="sr-only" for="map-position-filter">Filtrar camiones</label><select id="map-position-filter" wire:model.live="positionFilter" class="rounded-lg border border-gray-300 bg-white p-2 text-xs dark:border-gray-600 dark:bg-gray-950"><option value="all">Todos los camiones</option><option value="located">Con posición GPS</option><option value="missing">Sin posición GPS</option><option value="active">Activos en el sistema</option><option value="inactive">Inactivos en el sistema</option></select>
                    @error('positionFilter')<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($trucks as $truck)
                        @php($gps = $truck->latestGpsPosition)
                        <button type="button" wire:key="location-truck-{{ $truck->id }}" wire:click="selectTruck({{ $truck->id }})" x-on:click="detailsOpen = true" aria-pressed="{{ $selectedTruckId === $truck->id ? 'true' : 'false' }}" class="w-full border-l-4 p-3 text-left text-xs {{ $selectedTruckId === $truck->id ? 'border-primary-500 bg-primary-50 dark:bg-primary-950' : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                            <span class="flex items-center justify-between gap-2"><strong class="text-sm">{{ $truck->plate }}</strong><span class="{{ $gps ? 'text-success-600' : 'text-gray-500' }}">{{ $gps ? 'Con posición' : 'Sin posición' }}</span></span>
                            <span class="mt-1 block truncate text-gray-500">{{ $truck->conductor_name ?: 'Sin conductor informado' }}</span>
                            <span class="mt-2 flex items-center justify-between gap-2"><span>{{ $gps?->observed_at?->format('d/m/Y H:i:s') ?? 'Sin fecha de posición' }}</span><span class="max-w-28 truncate font-medium">{{ $gps?->details['speed'] ?? '—' }}</span></span>
                            <span class="mt-1 block truncate text-gray-500">{{ $gps?->details['address'] ?? 'Sin dirección informada' }}</span>
                            <span class="mt-1 block text-gray-500">{{ $truck->is_active ? 'Activo' : 'Inactivo' }} · Dueño: {{ $truck->owner?->name ?: 'Sin registrar' }}</span>
                        </button>
                    @empty<p class="p-5 text-sm text-gray-500">No se encontraron camiones.</p>@endforelse
                </div>
                <div class="flex items-center justify-between gap-2 border-t border-gray-200 p-2 text-xs text-gray-500 dark:border-gray-700"><x-filament::button size="xs" color="gray" wire:click="previousPage" :disabled="$trucks->onFirstPage()">Anterior</x-filament::button><span>{{ $trucks->currentPage() }} / {{ $trucks->lastPage() }}</span><x-filament::button size="xs" color="gray" wire:click="nextPage" :disabled="! $trucks->hasMorePages()">Siguiente</x-filament::button></div>
            </section>
            <section class="relative flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" aria-label="Consola del mapa">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold">Mapa de ubicaciones</h2>
                    <div class="flex items-center gap-3 text-xs"><label class="flex items-center gap-1"><input type="checkbox" checked x-on:change="window.dispatchEvent(new CustomEvent('fleet-map-places', {detail:{visible:$event.target.checked}}))">Mostrar lugares</label>@if ($selection)<button type="button" x-on:click="detailsOpen = !detailsOpen" class="font-semibold text-primary-600">Ficha del camión</button>@endif</div>
                </div>
                <div class="relative min-h-0 flex-1">
                    <div wire:ignore class="h-full" x-data="{
                        error: '', dispose: null,
                        async init() {
                            try {
                                await import(@js(\Illuminate\Support\Facades\Vite::asset('resources/js/fleet-map.js')));
                                this.dispose = await window.fleetGoogleMap.mount(this.$refs.canvas, @js(['apiKey' => config('services.google_maps.browser_key'), 'places' => $places, 'fleet' => $fleetPositions, 'initial' => ['truck' => $selection, 'points' => $points, 'route' => false]]), (message) => { this.error = message; }, (id) => { this.$wire.selectTruck(id); this.detailsOpen = true; });
                            } catch (error) { this.error = error.message; }
                        }, destroy() { this.dispose?.(); }
                    }">
                        <p x-show="error" x-cloak x-text="error" role="alert" class="absolute left-3 top-3 z-20 max-w-sm rounded-lg bg-danger-50 p-3 text-sm text-danger-700"></p>
                        <div x-ref="canvas" role="region" aria-label="Mapa Google de lugares y posiciones GPS" class="h-full min-h-80 w-full bg-gray-100 dark:bg-gray-800"></div>
                    </div>
                    @if ($selection)
                        <aside x-show="detailsOpen" class="fleet-detail absolute bottom-3 right-3 z-10 max-h-[70%] w-72 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900" aria-label="Ficha del camión">
                            <div class="sticky top-0 flex items-center justify-between gap-3 border-b border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"><div><h3 class="font-bold">{{ $selection['plate'] }}</h3><span class="text-xs text-gray-500">{{ $selection['active'] ? 'Activo' : 'Inactivo' }} en el sistema</span></div><button type="button" aria-label="Cerrar ficha" x-on:click="detailsOpen = false" class="rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-800"><x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4"/></button></div>
                            <dl class="divide-y divide-gray-100 px-3 text-xs dark:divide-gray-800">
                                @foreach (['Conductor asignado' => $selection['driver'] ?: 'Sin informar', 'Dueño' => $selection['owner'] ?: 'Sin registrar', 'Último reporte GPS' => $selection['gps']['at'] ?? 'Sin posición', 'Coordenadas' => isset($selection['gps']) ? $selection['gps']['lat'].', '.$selection['gps']['lng'] : 'Sin posición'] as $label => $value)<div class="py-2"><dt class="text-gray-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium">{{ $value }}</dd></div>@endforeach
                                @foreach (['speed' => 'Velocidad informada', 'event' => 'Evento GPS', 'observed_driver' => 'Conductor informado por GPS', 'address' => 'Dirección del GPS', 'heading' => 'Orientación', 'odometer' => 'Odómetro', 'nearest_place' => 'Lugar cercano', 'received_at' => 'Recepción del dato', 'buffered' => 'Buffer'] as $field => $label)@if (filled($selection['gps']['details'][$field] ?? null))<div class="py-2"><dt class="text-gray-500">{{ $label }}</dt><dd class="mt-1 break-words">{{ $selection['gps']['details'][$field] }}</dd></div>@endif@endforeach
                            </dl>
                        </aside>
                    @endif
                </div>
                <div class="border-t border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <p role="status" class="text-xs text-gray-500">{{ $positionMessage }}</p>
                    @if ($selection)
                        <form x-data="{ querying: false, requestError: '' }" x-on:submit.prevent="querying = true; requestError = ''; try { await $wire.showRoute() } catch (error) { requestError = 'No se pudo consultar el recorrido. Comprueba tu conexión y vuelve a intentarlo.' } finally { querying = false }" class="mt-2 flex flex-wrap items-end gap-2">
                            <label class="flex flex-col gap-1 text-xs">Fecha del recorrido<input type="date" wire:model="date" class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-950"></label><x-filament::button size="sm" type="submit" :loading-indicator="false" x-bind:disabled="querying || !$wire.selectedTruckId" icon="heroicon-o-map">Ver recorrido</x-filament::button><x-filament::button size="sm" color="gray" wire:click="selectTruck({{ $selectedTruckId }})">Última posición</x-filament::button><p x-cloak x-show="requestError" x-text="requestError" role="alert" class="w-full text-xs text-danger-600"></p>
                        </form>
                        @error('date')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                        @error('selectedTruckId')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                    @endif
                    @if ($routeSummary)<p class="mt-2 text-xs">Inicio: {{ $routeSummary['from'] }} · Fin: {{ $routeSummary['to'] }} · {{ $routeSummary['displayed'] }} de {{ $routeSummary['total'] }} posiciones válidas del día mostradas.</p>@if (($routeSummary['distinct_locations'] ?? 0) === 1)<p class="mt-1 text-xs text-warning-600">Todas las observaciones tienen las mismas coordenadas. El reporte no registra desplazamiento en esta fecha.</p>@endif @endif
                    <p wire:loading wire:target="selectTruck,showRoute" class="mt-1 text-xs text-gray-500">Consultando registros…</p>
                </div>
            </section>
        </div>
        <p class="text-xs text-gray-500">Camión con placa: última posición registrada · Rojo: puntos del recorrido · Azul: lugares de referencia. La lista se filtra; «Ver toda la flota» muestra las posiciones disponibles de todos los camiones.</p>
    </div>
</x-filament-panels::page>
