<div wire:init="check" style="display: flex; flex-direction: column; gap: 0.75rem;">
    {{-- Simplify's header model: title, subtitle, actions right. Room on the right for the window's close button. --}}
    <header style="display: flex; gap: 0.75rem; align-items: flex-start; padding-inline-end: 2rem;">
        <div style="flex: 1; min-width: 0;">
            <h2 class="fi-modal-heading">{{ $this->heading() }}</h2>
            <p class="fi-modal-description">{{ $this->from() }}</p>
        </div>
        @if ($result !== null)
            <x-filament::icon-button
                icon="tabler-refresh"
                color="gray"
                :label="__('simplify-explain::explain.check_again')"
                :tooltip="__('simplify-explain::explain.check_again')"
                wire:click="check"
                wire:loading.attr="disabled"
            />
        @endif
    </header>
    @if ($result === null)
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <x-filament::loading-indicator style="width: 1.25rem; height: 1.25rem;" />
            <span>{{ __('simplify-explain::explain.checking') }}</span>
        </div>
    @elseif (! $result['ok'])
        <x-filament::section icon="tabler-alert-triangle" icon-color="warning" compact>
            {{ $result['error'] }}
        </x-filament::section>
    @else
        <x-filament::section
            :icon="$result['found'] ? 'tabler-alert-circle' : 'tabler-circle-check'"
            :icon-color="$result['found'] ? 'danger' : 'success'"
            :heading="$result['found'] ? __('simplify-explain::explain.found') : __('simplify-explain::explain.fine')"
            compact
        >
            {{ $result['summary'] }}
        </x-filament::section>

        @if ($result['issues'] !== [])
            @unless ($result['found'])
                <div style="font-weight: 500;">{{ __('simplify-explain::explain.noticed') }}</div>
            @endunless
            @foreach ($result['issues'] as $index => $issue)
                <x-filament::section
                    :heading="$issue['title']"
                    collapsible
                    :collapsed="! ($result['found'] && $index === 0)"
                    compact
                    wire:key="issue-{{ $index }}"
                >
                    <p>{{ $issue['cause'] }}</p>
                    @if ($issue['steps'] !== [])
                        <div style="margin-top: 0.75rem; font-weight: 500;">{{ __('simplify-explain::explain.try') }}</div>
                        <ol style="list-style: decimal; padding-left: 1.25rem; margin-top: 0.25rem;">
                            @foreach ($issue['steps'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    @endif
                </x-filament::section>
            @endforeach
        @endif

        @if ($result['found'] && ($support = $this->supportUrl()))
            <div>
                <x-filament::button tag="a" :href="$support" target="_blank" rel="noopener" color="gray" size="sm" icon="tabler-lifebuoy">
                    {{ __('simplify-explain::explain.ticket') }}
                </x-filament::button>
            </div>
        @endif

        @if ($result['model'])
            <div style="font-size: 0.75rem; opacity: 0.7;">
                {{ trans_choice('simplify-explain::explain.checked_by', $result['lines'], ['model' => $result['model'], 'lines' => $result['lines']]) }}
            </div>
        @endif
    @endif
</div>
