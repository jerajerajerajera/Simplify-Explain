<div class="simplify-explain-problem-host">
    <x-filament-actions::modals />
</div>

@script
<script>
    if (! window.simplifyExplainProblems) {
        window.simplifyExplainProblems = true;
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-simplify-explain]');
            if (! button) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            const host = document.querySelector('.simplify-explain-problem-host')?.closest('[wire\\:id]');
            if (host) {
                Livewire.find(host.getAttribute('wire:id'))?.call('open', button.dataset.simplifyExplain);
            }
        }, true);
    }
</script>
@endscript
