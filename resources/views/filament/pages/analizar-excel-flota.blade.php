<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <label class="flex flex-col gap-2 text-sm font-medium">¿Qué deseas importar?<select wire:model.live="kind" class="rounded-lg border border-gray-300 bg-white p-3 dark:border-gray-600 dark:bg-gray-900"><option value="trucks">Camiones: placa y conductor opcional</option><option value="drivers">Solo conductores: nombre y datos opcionales</option></select></label>
        <p class="text-gray-600 dark:text-gray-400">{{ $kind === 'drivers' ? 'Registra conductores sin asignar camiones. Revisa nombres y documentos antes de guardar.' : 'Placa obligatoria, conductor y dueño opcionales. Se agregan placas nuevas y tú decides qué cambios de conductor aprobar. Los dueños se administran manualmente.' }}</p>
        <ol class="grid gap-3 sm:grid-cols-3" aria-label="Pasos de la importación">@foreach (['Cargar Excel', 'Seleccionar columnas', 'Revisar registros'] as $step => $label)<li class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-400">{{ $step + 1 }}</span><span class="text-sm font-medium">{{ $label }}</span></li>@endforeach</ol>
        <x-filament::section heading="1. Selecciona tu Excel">
            <div class="flex flex-col gap-4">
                <label for="excel-file" class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center hover:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500 dark:border-gray-600 dark:bg-gray-900">
                    <x-filament::icon icon="heroicon-o-arrow-up-tray" class="h-8 w-8 text-primary-600"/><span class="font-semibold">Seleccionar archivo Excel</span><span class="text-xs text-gray-500">Formato .xlsx · Hasta 30 MB</span><input id="excel-file" type="file" wire:model.live="file" accept=".xlsx" class="sr-only">
                </label>
                @error('file')<p role="alert" class="text-sm text-danger-600">{{ $message }}</p>@enderror
                <p wire:loading wire:target="file" role="status" class="text-sm text-gray-500">Subiendo el Excel…</p>
                @if ($file)<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-success-50 p-4 dark:bg-success-950"><div><p class="text-sm font-semibold">Archivo listo para analizar</p><p class="break-all text-sm">{{ $file->getClientOriginalName() }}</p></div><x-filament::button color="gray" wire:click="clearFile" wire:loading.attr="disabled">Cambiar archivo</x-filament::button></div>@endif
                @if ($sheets)
                    <details class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700"><summary class="cursor-pointer">Cambiar hoja o fila de encabezados</summary><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="flex flex-col gap-2">Hoja<select wire:model.live="selectedSheet" class="rounded-lg border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-900">@foreach ($sheets as $sheet)<option value="{{ $sheet }}">{{ $sheet }}</option>@endforeach</select></label><label class="flex flex-col gap-2">Fila de encabezados<input type="number" min="1" wire:model.live.blur="headerRow" class="rounded-lg border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-900"></label></div><p class="mt-3 text-xs text-gray-500">Después de cambiar estos datos, vuelve a analizar el archivo.</p></details>
                @endif
                @error('headerRow')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                <div class="flex justify-end"><x-filament::button wire:key="analyze-{{ $file ? 'ready' : 'empty' }}" wire:click="analyze" :disabled="! $file" wire:loading.attr="disabled" wire:target="file,analyze" icon="heroicon-o-magnifying-glass">Analizar archivo</x-filament::button></div>
                <p wire:loading wire:target="analyze" role="status" class="text-sm text-gray-500">Leyendo el archivo…</p>
            </div>
        </x-filament::section>
        @if ($analysis)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><p class="text-sm text-gray-500">Archivo analizado. Continúa en la ventana de revisión.</p><x-filament::button wire:click="openReview">Continuar revisión</x-filament::button></div>
        @endif
        @if ($importResult && ! $preview)
            <p class="rounded-xl bg-success-50 p-4 text-sm text-success-800 dark:bg-success-950 dark:text-success-300">Registro exitoso: {{ $importResult['trucks_created'] ?? 0 }} camiones agregados · {{ $importResult['trucks_updated'] ?? 0 }} cambios de conductor · {{ $importResult['drivers_created'] ?? 0 }} fichas de conductor agregadas. <a class="font-semibold underline" href="{{ \App\Filament\Resources\Trucks\TruckResource::getUrl() }}">Ver camiones</a></p>
        @endif
        <x-filament::modal id="fleet-review" width="5xl" sticky-header sticky-footer :close-by-clicking-away="false">
            <x-slot name="heading">{{ $wizardStep === 2 ? 'Selecciona las columnas' : ($importKind === 'drivers' ? '3. Revisa los conductores' : '3. Revisa las placas nuevas y cambios') }}</x-slot>
        @if ($analysis && $wizardStep === 2)
            <div>
                <div class="flex flex-col gap-5"><p class="text-sm text-gray-500">{{ number_format($analysis['data_rows']) }} filas · Hoja {{ $analysis['sheet'] }} · Encabezados en la fila {{ $analysis['header_row'] }}. Asigna cada columna a una variable. En camiones, Placa es obligatoria; en conductores, Nombre es obligatorio. Usa Fecha y Hora para identificar el último conductor del GPS.</p>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700"><table class="w-full min-w-[540px] text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="p-4">Columna del Excel</th><th class="p-4">Ejemplos</th><th class="p-4">Corresponde a</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($columns as $position => $column)<tr wire:key="mapping-row-{{ $position }}"><th scope="row" class="p-4">{{ $column['name'] }}</th><td class="max-w-xs break-words p-4 text-gray-500">{{ implode(' · ', $column['sample']) ?: 'Sin valores' }}</td><td class="p-4"><label for="mapping-{{ $position }}" class="sr-only">Columna {{ $column['name'] }}</label><select id="mapping-{{ $position }}" wire:model.live="mapping.{{ $position }}" class="w-full rounded-lg border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-900"><option value="discard">No utilizar</option>@if ($kind === 'trucks')<option value="truck.plate">Placa (obligatoria)</option><option value="driver.name">Conductor (opcional)</option><option value="date">Fecha</option><option value="time">Hora</option><option value="datetime">Fecha y hora</option>@else<option value="driver.name">Nombre del conductor (obligatorio)</option><option value="document">Documento (opcional)</option><option value="phone">Teléfono (opcional)</option>@endif</select>@error("mapping.$position")<p class="text-xs text-danger-600">{{ $message }}</p>@enderror</td></tr>@endforeach
                    </tbody></table></div>
                    @error('mapping')<p role="alert" class="text-sm text-danger-600">{{ $message }}</p>@enderror

                </div>
            </div>
        @endif
            @if ($wizardStep === 3)
                @include('filament.pages.fleet-import-review')
            @endif
            @error('import')<p role="alert" class="mt-3 text-sm text-danger-600">{{ $message }}</p>@enderror
            <x-slot name="footer">
                <div class="flex w-full flex-col gap-3">
                    @if ($wizardStep === 3 && $this->hasReviewActions())
                        <label class="flex items-start gap-3 text-sm"><input type="checkbox" wire:model="reviewConfirmed" class="mt-1 rounded border-gray-300"><span>Revisé los datos. Se guardarán los registros nuevos y solamente los cambios marcados. Los camiones sin conductor también se registran.</span></label>
                        @error('reviewConfirmed')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                    @endif
                    <div class="flex flex-wrap justify-end gap-3">
                        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'fleet-review' })">Cerrar</x-filament::button>
                        @if ($wizardStep === 2)<x-filament::button wire:click="prepareImport" wire:loading.attr="disabled" wire:target="prepareImport">Revisar registros</x-filament::button>
                        @else <x-filament::button color="gray" wire:click="backToColumns" wire:loading.attr="disabled">Cambiar columnas</x-filament::button>
                            @if ($this->hasReviewActions())<x-filament::button wire:click="confirmImport" wire:loading.attr="disabled" wire:target="confirmImport">Confirmar y guardar</x-filament::button>@endif
                        @endif
                    </div>
                    <p wire:loading wire:target="prepareImport,confirmImport" role="status" class="text-sm text-gray-500">Procesando…</p>
                </div>
            </x-slot>
        </x-filament::modal>
        <x-filament::modal id="fleet-relationships" width="3xl" sticky-footer>
            <x-slot name="heading">Relaciones observadas: revisar antes de asignar</x-slot>
            <div class="flex flex-col gap-4 text-sm">
                <p class="text-gray-500">Un nombre repetido no prueba que sea la misma persona. Estos avisos no cambian el conductor actual ni crean propietarios.</p>
                @foreach ($preview['plates_with_multiple_drivers'] ?? [] as $case)
                    <div class="rounded-xl border border-warning-200 p-4 dark:border-warning-800"><p class="font-semibold">Placa {{ $case['plate'] }} · {{ count($case['drivers']) }} nombres encontrados</p><p class="mt-2">{{ implode(' · ', $case['drivers']) }}</p><p class="mt-2 text-xs text-gray-500">Para un camión nuevo, puedes elegir un nombre o dejarlo pendiente en la revisión. Para uno existente, revisa el cambio propuesto y su fecha en esta importación. Los empates o fechas inválidas se resuelven manualmente desde Camiones.</p></div>
                @endforeach
                @foreach ($preview['names_on_multiple_plates'] ?? [] as $case)
                    <div class="rounded-xl border border-warning-200 p-4 dark:border-warning-800"><p class="font-semibold">{{ $case['name'] }} · {{ count($case['plates']) }} placas</p><p class="mt-2">{{ implode(' · ', $case['plates']) }}</p><p class="mt-2 text-xs text-gray-500">Confirmar si hubo un cambio de camión, un homónimo, un alias del GPS o un dato incorrecto. No se fusionan personas por nombre.</p></div>
                @endforeach
            </div>
            <x-slot name="footer"><x-filament::button wire:click="openReview">Volver a la revisión</x-filament::button></x-slot>
        </x-filament::modal>
        <x-filament::modal id="fleet-success" width="lg" icon="heroicon-o-check-circle" icon-color="success">
            <x-slot name="heading">Registro exitoso</x-slot>
            @if ($importResult && $importKind === 'drivers')
                <p>{{ $importResult['drivers_created'] ?? 0 }} conductores nuevos registrados. Total: {{ $importResult['drivers_total'] ?? 0 }} conductores. No se asignaron camiones.</p>
            @elseif ($importResult)
                <p class="text-sm">{{ $importResult['trucks_updated'] ?? 0 }} cambios de conductor aprobados · {{ $importResult['updates_skipped'] ?? 0 }} cambios omitidos.</p>
                <div class="flex flex-col gap-3 text-sm"><p><strong>{{ $importResult['trucks_created'] }}</strong> camiones nuevos registrados y <strong>{{ $importResult['driver_names_created'] ?? 0 }}</strong> nombres de conductor nuevos.</p><p>Total en el sistema: <strong>{{ $importResult['trucks_total'] ?? '—' }}</strong> camiones y <strong>{{ $importResult['driver_names_total'] ?? '—' }}</strong> nombres distintos de conductor.</p><p class="text-gray-500">{{ $importResult['trucks_without_driver'] ?? 0 }} camiones están sin conductor informado. Puedes completarlo después desde Camiones → Editar.</p></div>
            @endif
            <x-slot name="footer"><div class="flex flex-wrap justify-end gap-3"><x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'fleet-success' })">Aceptar</x-filament::button><x-filament::button tag="a" :href="\App\Filament\Resources\Trucks\TruckResource::getUrl()">Ver camiones</x-filament::button></div></x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
