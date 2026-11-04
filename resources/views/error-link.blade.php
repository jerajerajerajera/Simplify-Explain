{{-- Pelican's 500 page, for root admins with a saved key: the emergency page reads the panel log, also while the panel is broken. --}}
@include('simplify-explain::styles')
<a href="{{ $url }}" class="sx-error-link">
    <x-filament::icon icon="tabler-message-question" />
    <span>
        <strong>{{ __('simplify-explain::explain.error_page') }}</strong><br>
        <span>{{ __('simplify-explain::explain.error_page_hint') }}</span>
    </span>
</a>
