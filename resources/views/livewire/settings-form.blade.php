{{-- No <form>: in Simplify settings this sits inside Simplify's own form. Save comes from the title bar. --}}
<div data-simplify-unsaved="data" data-simplify-save="save">
    {{ $this->form }}

    <x-filament-actions::modals />
</div>
