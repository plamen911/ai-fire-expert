<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="p-0! lg:p-0! overflow-hidden max-h-dvh">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
