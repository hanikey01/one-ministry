<x-layouts.workspace surface="member" :title="$title ?? null" :ministry-name="$ministryName ?? null" :primary-color="$primaryColor ?? null" :accent-color="$accentColor ?? null" :features="$features ?? []">
    {{ $slot }}
</x-layouts.workspace>
