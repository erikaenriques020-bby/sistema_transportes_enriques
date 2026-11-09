<x-filament-panels::page>
    <div class="grid gap-3 sm:grid-cols-3">@foreach ([['Camiones registrados', $truckCount], ['Nombres distintos de conductor', $conductors->count()], ['Camiones sin conductor', $missingDrivers->count()]] as [$label, $value])<div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></div>@endforeach</div>
    <p class="text-sm text-gray-500">Nombres informados en la lista de camiones y sus placas relacionadas. Se agrupan por nombre; no constituyen una identificación personal.</p>
    <x-filament::section>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-gray-200 dark:border-gray-700"><th class="p-4">Conductor</th><th class="p-4">Placas</th></tr></thead><tbody>
        @forelse ($conductors as $conductor)<tr class="border-b border-gray-100 dark:border-gray-800"><td class="p-4 font-medium">{{ $conductor['name'] }}</td><td class="p-4">{{ implode(', ', $conductor['plates']) }}</td></tr>@empty<tr><td colspan="2" class="p-6 text-gray-500">Todavía no hay conductores informados. Puedes agregar camiones con conductor vacío.</td></tr>@endforelse
        </tbody></table></div>
    </x-filament::section>
    @if ($truckCount === 0)<p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800">Aún no se ha confirmado ningún registro de camiones. Analizar un Excel muestra la vista previa; pulsa Guardar placas nuevas para registrar los datos y verlos aquí.</p>@endif
    @if ($missingDrivers->isNotEmpty())
        <x-filament::section heading="Camiones registrados sin conductor">
            <p class="mb-4 text-sm text-gray-500">Estos camiones sí forman parte del total registrado. Puedes completar el conductor después de consultarlo.</p>
            <div class="flex flex-wrap gap-3">@foreach ($missingDrivers as $truck)<a class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium underline dark:border-gray-700" href="{{ \App\Filament\Resources\Trucks\TruckResource::getUrl('edit', ['record' => $truck->id]) }}">{{ $truck->plate }} · Agregar conductor</a>@endforeach</div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
