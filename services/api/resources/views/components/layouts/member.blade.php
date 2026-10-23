<x-layouts.workspace surface="member" :title="$title ?? null" :ministry-name="$ministryName ?? null" :features="$features ?? []">
    {{ $slot }}
</x-layouts.workspace>
