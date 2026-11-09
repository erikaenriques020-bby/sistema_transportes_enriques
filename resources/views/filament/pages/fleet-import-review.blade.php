<div class="flex flex-col gap-4">
    @if ($preview)
        @if ($importKind === 'drivers')
            <p class="text-sm">{{ $preview['unique_names'] }} registros distintos · {{ $preview['existing_count'] }} existentes · {{ count($preview['drivers']) }} conductores nuevos.</p>
            <p class="text-xs text-gray-500">Un documento identifica una ficha. Sin documento se comparan nombres normalizados para evitar duplicados; los homónimos requieren revisión manual. No se crean dueños ni se asignan camiones.</p>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Nombre</th><th class="p-3">Documento</th><th class="p-3">Teléfono</th></tr></thead><tbody>@foreach ($reviewRows as $person)<tr><td class="p-3">{{ $person['name'] }}</td><td class="p-3">{{ $person['document_number'] ?: 'Sin documento' }}</td><td class="p-3">{{ $person['phone'] ?: '—' }}</td></tr>@endforeach</tbody></table></div>
            @if (! count($preview['drivers']))<p>No se encontraron conductores nuevos para registrar.</p>@endif
            <div class="flex items-center justify-between"><x-filament::button color="gray" wire:click="previousReviewPage" :disabled="$reviewPage <= 1">Anterior</x-filament::button><span>Página {{ $reviewPage }} de {{ $reviewPages }}</span><x-filament::button color="gray" wire:click="nextReviewPage" :disabled="$reviewPage >= $reviewPages">Siguiente</x-filament::button></div>
            @foreach (array_slice($preview['invalid'], 0, 10) as $issue)<p class="text-sm text-warning-600">Fila {{ $issue['row'] }}: {{ $issue['reason'] }}</p>@endforeach
        @else
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
            @foreach ([['Camiones en Excel', $preview['unique_plates']], ['Con conductor', $preview['trucks_with_driver']], ['Sin conductor', $preview['trucks_without_driver']], ['Nombres distintos', $preview['driver_names']], ['Ya registrados', $preview['existing_count']], ['Camiones nuevos', count($preview['trucks'])]] as [$label, $value])
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><p class="text-xs text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-semibold">{{ $value }}</p></div>
            @endforeach
        </div>
        <p class="text-xs leading-5 text-gray-500">Todos los camiones con placa válida cuentan, tengan o no conductor. Un nombre puede aparecer en varias placas; por eso la cantidad de nombres no indica cuántos camiones tienen conductor.</p>
        @if (count($preview['plates_with_multiple_drivers'] ?? []) || count($preview['names_on_multiple_plates'] ?? []))
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-warning-50 p-4 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-300"><p>Revisar relaciones: {{ count($preview['plates_with_multiple_drivers'] ?? []) }} placas con varios nombres y {{ count($preview['names_on_multiple_plates'] ?? []) }} nombres asociados a varias placas. Son observaciones del reporte, no asignaciones confirmadas.</p><x-filament::button color="warning" size="sm" wire:click="openRelationships" icon="heroicon-o-exclamation-triangle">Ver casos detectados</x-filament::button></div>
        @endif
        @if ($preview['invalid'])<details class="rounded-lg bg-warning-50 p-3 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-300"><summary class="cursor-pointer">{{ count($preview['invalid']) }} observaciones para revisar</summary><ul class="mt-2 flex flex-col gap-1">@foreach (array_slice($preview['invalid'], 0, 10) as $issue)<li>Fila {{ $issue['row'] }}: {{ $issue['reason'] }}</li>@endforeach</ul></details>@endif
        @if (! $preview['trucks'])
            <p role="status" class="rounded-xl border border-success-200 bg-success-50 p-5 font-medium text-success-800 dark:border-success-800 dark:bg-success-950 dark:text-success-300">Estos registros ya existen. No se encontraron placas nuevas. Revisa abajo si hay cambios de conductor pendientes.</p>
            @if ($preview['unique_plates'] === 0)<p class="text-sm text-gray-500">El archivo no contiene placas válidas en la columna seleccionada. Revisa las columnas y los encabezados.</p>@endif
        @else
            <p class="text-sm font-medium">Por registrar: {{ count($preview['trucks']) }} camiones nuevos · {{ $preview['new_driver_names'] }} nombres de conductor nuevos.</p>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700"><table class="w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Placa nueva</th><th class="px-4 py-3">Conductor</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($reviewRows as $index => $truck)<tr wire:key="review-{{ $importId }}-{{ $index }}"><th class="px-4 py-3" scope="row">{{ $truck['plate'] }}</th><td class="px-4 py-3">
                @if (count($truck['drivers']) > 1)<label for="review-driver-{{ $index }}" class="mb-1 block text-xs text-warning-700">Varios nombres: elige uno o déjalo pendiente.</label><select id="review-driver-{{ $index }}" wire:model.live="truckChoices.{{ $index }}.conductor_name" class="w-full rounded-lg border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-900"><option value="">Sin conductor informado</option>@foreach ($truck['drivers'] as $driver)<option value="{{ $driver }}">{{ $driver }}</option>@endforeach</select>
                @else <span class="{{ $truck['conductor_name'] ? '' : 'text-warning-700 dark:text-warning-400' }}">{{ $truck['conductor_name'] ?: 'Sin conductor informado · se registrará igualmente' }}</span> @endif
                @error("truckChoices.$index.conductor_name")<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
            </td></tr>@endforeach
            </tbody></table></div>
            <div class="flex items-center justify-between gap-3"><x-filament::button color="gray" size="sm" wire:key="previous-review-{{ $reviewPage }}" wire:click="previousReviewPage" :disabled="$reviewPage <= 1" wire:loading.attr="disabled">Anterior</x-filament::button><span class="text-xs text-gray-500">Página {{ $reviewPage }} de {{ $reviewPages }} · 6 camiones por página</span><x-filament::button color="gray" size="sm" wire:key="next-review-{{ $reviewPage }}" wire:click="nextReviewPage" :disabled="$reviewPage >= $reviewPages" wire:loading.attr="disabled">Siguiente</x-filament::button></div>
        @endif
        @if (count($preview['updates'] ?? []))
            <div class="rounded-xl border border-warning-200 p-4 dark:border-warning-800">
                <h3 class="font-semibold">Cambios de conductor: {{ count($preview['updates']) }}</h3>
                <p class="mt-2 text-sm text-gray-500">Se propone el último nombre según la fecha y hora del GPS, aunque las filas estén desordenadas. Marca únicamente los cambios que deseas aprobar. El dueño y el estado del camión se conservan.</p>
                <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Actualizar</th><th class="p-3">Placa</th><th class="p-3">Actual</th><th class="p-3">Propuesto por GPS</th><th class="p-3">Fecha y hora GPS</th></tr></thead><tbody>
                @foreach ($updateRows as $index => $change)<tr wire:key="change-{{ $importId }}-{{ $index }}"><td class="p-3"><input aria-label="Actualizar conductor de {{ $change['plate'] }}" type="checkbox" wire:model.live="updateChoices.{{ $index }}.approve" class="rounded border-gray-300"></td><th class="p-3">{{ $change['plate'] }}</th><td class="p-3">{{ $change['previous_name'] ?: 'Sin conductor' }}</td><td class="p-3">{{ $change['conductor_name'] }}</td><td class="p-3">{{ $change['latest_at'] ?: 'Sin fecha GPS' }}@if ($change['date_warning'])<p class="mt-1 text-xs text-warning-600">{{ $change['date_warning'] }}</p>@endif</td></tr>@endforeach
                </tbody></table></div>
                <div class="mt-3 flex items-center justify-between"><x-filament::button color="gray" wire:click="previousUpdatesPage" :disabled="$updatesPage <= 1">Anterior</x-filament::button><span class="text-xs">Página {{ $updatesPage }} de {{ $updatesPages }} · 6 cambios por página</span><x-filament::button color="gray" wire:click="nextUpdatesPage" :disabled="$updatesPage >= $updatesPages">Siguiente</x-filament::button></div>
            </div>
        @endif
        @if (count($preview['observations'] ?? []))<p class="text-sm text-gray-500">{{ count($preview['observations']) }} camiones mantienen el mismo conductor. Al confirmar se actualizará únicamente su fecha GPS de referencia.</p>@endif
        @if (count($preview['review_issues'] ?? []))
            <details class="rounded-xl bg-warning-50 p-4 text-sm dark:bg-warning-950"><summary class="cursor-pointer">{{ count($preview['review_issues']) }} avisos: conductor actual conservado</summary><div class="mt-3 max-h-64 overflow-y-auto">@foreach ($preview['review_issues'] as $issue)<p class="mb-2"><strong>{{ $issue['plate'] }}</strong>: {{ $issue['reason'] }}</p>@endforeach</div></details>
        @endif
        @endif
    @endif
</div>

