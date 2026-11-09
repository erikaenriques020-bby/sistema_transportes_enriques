<x-filament-panels::page>
    <div class="flex flex-col gap-5">
        <x-filament::section heading="Filtros del reporte" description="Selecciona varios camiones para comparar sus posiciones y eventos GPS dentro del período.">
            <form wire:submit="runReport" class="flex flex-col gap-4">
                <fieldset><legend class="mb-2 text-sm font-medium">Camiones</legend><div class="grid max-h-64 gap-2 overflow-y-auto rounded-lg border border-gray-200 p-3 sm:grid-cols-2 lg:grid-cols-3 dark:border-gray-700">
                    @forelse ($trucks as $truck)<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="truckIds" value="{{ $truck->id }}" class="rounded border-gray-300 text-primary-600"><span>{{ $truck->plate }}{{ $truck->conductor_name ? ' · '.$truck->conductor_name : '' }}</span></label>@empty<p class="text-sm text-gray-500">No hay camiones disponibles.</p>@endforelse
                </div></fieldset>
                @error('truckIds')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                @error('truckIds.*')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                <div class="grid gap-4 md:grid-cols-3 md:items-end">
                    <label class="flex flex-col gap-1 text-sm">Desde<input type="date" wire:model="dateFrom" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label>
                    <label class="flex flex-col gap-1 text-sm">Hasta<input type="date" wire:model="dateTo" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label>
                    <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Consultar camiones</x-filament::button>
                </div>
                @error('dateFrom')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                @error('dateTo')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
            </form>
        </x-filament::section>

        @if ($positions)
            <div><x-filament::button wire:click="exportExcel" icon="heroicon-o-arrow-down-tray" wire:loading.attr="disabled" wire:target="exportExcel">Exportar Excel</x-filament::button></div>
            <p class="text-sm text-gray-500">Período consultado: {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }} · Datos GPS de archivos importados; no es seguimiento en tiempo real.</p>
            <x-filament::section heading="Resumen por camión">
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b dark:border-gray-700"><th class="p-3">Placa</th><th class="p-3">Conductor informado</th><th class="p-3">Posiciones válidas</th><th class="p-3">Primera observación</th><th class="p-3">Última observación</th></tr></thead><tbody>
                    @foreach ($summaries as $summary)<tr class="border-b dark:border-gray-800"><td class="p-3 font-medium">{{ $summary['plate'] }}</td><td class="p-3">{{ $summary['driver'] ?: '—' }}</td><td class="p-3">{{ number_format($summary['count']) }}</td><td class="p-3">{{ $summary['first'] ? \Illuminate\Support\Carbon::parse($summary['first'])->format('d/m/Y H:i:s') : 'Sin datos' }}</td><td class="p-3">{{ $summary['last'] ? \Illuminate\Support\Carbon::parse($summary['last'])->format('d/m/Y H:i:s') : 'Sin datos' }}</td></tr>@endforeach
                </tbody></table></div>
            </x-filament::section>
            <x-filament::section heading="Ubicaciones y eventos GPS de los camiones seleccionados">
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b dark:border-gray-700"><th class="p-3">Camión</th><th class="p-3">Fecha y hora GPS</th><th class="p-3">Latitud</th><th class="p-3">Longitud</th><th class="p-3">Evento</th><th class="p-3">Velocidad</th><th class="p-3">Dirección</th><th class="p-3">Fuente</th></tr></thead><tbody>
                    @forelse ($positions as $position)<tr class="border-b dark:border-gray-800"><td class="p-3 font-medium">{{ $position->truck?->plate ?? '—' }}</td><td class="p-3 whitespace-nowrap">{{ $position->observed_at->format('d/m/Y H:i:s') }}</td><td class="p-3">{{ $position->latitude }}</td><td class="p-3">{{ $position->longitude }}</td><td class="p-3">{{ $position->details['event'] ?? '—' }}</td><td class="p-3">{{ $position->details['speed'] ?? '—' }}</td><td class="p-3">{{ $position->details['address'] ?? '—' }}</td><td class="p-3">{{ $position->fleetImport?->original_name ?? '—' }}{{ $position->source_row ? ' · fila '.$position->source_row : '' }}</td></tr>
                    @empty<tr><td colspan="8" class="p-5 text-center text-gray-500">No hay posiciones GPS válidas para los camiones y período seleccionados.</td></tr>@endforelse
                </tbody></table></div>
                @if ($positions->hasPages())<div class="mt-4">{{ $positions->links() }}</div>@endif
            </x-filament::section>
        @elseif ($hasQueried)
            <x-filament::section><p class="text-sm text-gray-500">No hay datos GPS para mostrar.</p></x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
