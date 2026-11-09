<x-filament-panels::page>
    <div class="flex flex-col gap-5">
        <x-filament::section heading="Filtros del reporte" description="Consulta las ubicaciones y eventos GPS importados para un camión y período.">
            <form wire:submit="runReport" class="grid gap-4 md:grid-cols-4 md:items-end">
                <label class="flex flex-col gap-1 text-sm">Camión
                    <select wire:model="truckId" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Selecciona un camión</option>
                        @foreach ($trucks as $truck)<option value="{{ $truck->id }}">{{ $truck->plate }}{{ $truck->conductor_name ? ' · '.$truck->conductor_name : '' }}</option>@endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm">Desde<input type="date" wire:model="dateFrom" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label>
                <label class="flex flex-col gap-1 text-sm">Hasta<input type="date" wire:model="dateTo" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label>
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Consultar</x-filament::button>
            </form>
            @error('truckId')<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
            @error('dateFrom')<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
            @error('dateTo')<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
        </x-filament::section>

        @if ($positions)
            <div><x-filament::button wire:click="exportExcel" icon="heroicon-o-arrow-down-tray" wire:loading.attr="disabled" wire:target="exportExcel">Exportar Excel</x-filament::button></div>
            <p class="text-sm text-gray-500">Período consultado: {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m/Y') }} · Datos GPS de archivos importados; no es seguimiento en tiempo real.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-filament::section heading="Posiciones válidas"><p class="text-2xl font-semibold">{{ number_format($summary['count']) }}</p></x-filament::section>
                <x-filament::section heading="Primera observación"><p class="text-sm">{{ $summary['first'] ? \Illuminate\Support\Carbon::parse($summary['first'])->format('d/m/Y H:i:s') : 'Sin datos' }}</p></x-filament::section>
                <x-filament::section heading="Última observación"><p class="text-sm">{{ $summary['last'] ? \Illuminate\Support\Carbon::parse($summary['last'])->format('d/m/Y H:i:s') : 'Sin datos' }}</p></x-filament::section>
            </div>
            <x-filament::section heading="Ubicaciones y eventos GPS">
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b dark:border-gray-700"><th class="p-3">Fecha y hora GPS</th><th class="p-3">Latitud</th><th class="p-3">Longitud</th><th class="p-3">Evento</th><th class="p-3">Velocidad</th><th class="p-3">Dirección</th><th class="p-3">Lugar cercano</th><th class="p-3">Fuente</th></tr></thead><tbody>
                    @forelse ($positions as $position)<tr class="border-b dark:border-gray-800"><td class="p-3 whitespace-nowrap">{{ $position->observed_at->format('d/m/Y H:i:s') }}</td><td class="p-3">{{ $position->latitude }}</td><td class="p-3">{{ $position->longitude }}</td><td class="p-3">{{ $position->details['event'] ?? '—' }}</td><td class="p-3">{{ $position->details['speed'] ?? '—' }}</td><td class="p-3">{{ $position->details['address'] ?? '—' }}</td><td class="p-3">{{ $position->details['nearest_place'] ?? '—' }}</td><td class="p-3">{{ $position->fleetImport?->original_name ?? '—' }}{{ $position->source_row ? ' · fila '.$position->source_row : '' }}</td></tr>
                    @empty<tr><td colspan="8" class="p-5 text-center text-gray-500">No hay posiciones GPS válidas para este camión y período.</td></tr>@endforelse
                </tbody></table></div>
                @if ($positions->hasPages())<div class="mt-4">{{ $positions->links() }}</div>@endif
            </x-filament::section>
        @elseif ($hasQueried)
            <x-filament::section><p class="text-sm text-gray-500">No hay datos GPS para mostrar.</p></x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
