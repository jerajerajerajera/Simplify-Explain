{{-- Pelican's own Health page, without Simplify: explains every check that fails or warns; opened by ProblemHost. --}}
<x-filament::button color="gray" icon="tabler-message-question" :data-simplify-explain="$problem">
    {{ __('simplify-explain::explain.button') }}
</x-filament::button>
