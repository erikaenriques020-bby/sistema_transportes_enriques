<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section heading="Camiones registrados"><p class="text-3xl font-semibold">{{ $truckCount }}</p></x-filament::section>
        <x-filament::section heading="Sin conductor informado"><p class="text-3xl font-semibold">{{ $withoutDriver }}</p></x-filament::section>
        <x-filament::section heading="Conductores"><p class="text-3xl font-semibold">{{ $driverCount }}</p></x-filament::section>
        <x-filament::section heading="Dueños"><p class="text-3xl font-semibold">{{ $ownerCount }}</p></x-filament::section>
        <x-filament::section heading="Última importación"><p class="text-sm">{{ $latestImport?->imported_at?->timezone('America/La_Paz')->format('d/m/Y H:i') ?? 'Sin importaciones confirmadas' }}</p></x-filament::section>
    </div>
    <x-filament::section heading="Actualiza tu lista de camiones">
        <div class="flex flex-col gap-4"><p class="text-sm text-gray-500">Carga el Excel del GPS, asigna las columnas de placa, conductor, fecha y hora. Revisa las placas nuevas y aprueba los cambios de conductor antes de guardar. Los dueños se registran manualmente.</p><div class="flex flex-wrap gap-3"><x-filament::button tag="a" :href="\App\Filament\Pages\AnalizarExcelFlota::getUrl()">Cargar Excel</x-filament::button><x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Trucks\TruckResource::getUrl()">Ver camiones</x-filament::button></div></div>
    </x-filament::section>
</x-filament-panels::page>
