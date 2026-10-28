<?php

declare(strict_types=1);

namespace Simplify\Explain\Filament;

use App\Models\Node;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Simplify\Explain\Services\Explainer;

/** The Explain buttons and their popup: for a server's console, the panel log and a node's Wings log. */
final class ExplainAction
{
    public static function console(): Action
    {
        return self::make('simplify-explain', __('simplify-explain::explain.button'))
            ->visible(fn () => ($server = self::server()) !== null && Explainer::mayExplainConsole(user(), $server))
            ->modalContent(fn () => view('simplify-explain::modal', ['source' => 'console', 'serverId' => self::server()?->id]));
    }

    public static function panelLog(): Action
    {
        return self::make('simplify-explain-panel', __('simplify-explain::explain.panel_button'))
            ->visible(fn () => Explainer::mayExplainLogs(user()))
            ->modalContent(fn () => view('simplify-explain::modal', ['source' => 'panel']));
    }

    public static function wingsLog(): Action
    {
        return self::make('simplify-explain-wings', __('simplify-explain::explain.wings_button'))
            ->visible(fn () => Explainer::mayExplainLogs(user()))
            ->modalContent(fn ($livewire) => view('simplify-explain::modal', [
                'source' => 'wings',
                'nodeId' => self::node($livewire)?->id,
            ]));
    }

    private static function make(string $name, string $label): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('tabler-message-question')
            ->color('gray')
            ->modalHeading(__('simplify-explain::explain.heading'))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('simplify-explain::explain.close'));
    }

    private static function server(): ?Server
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Server ? $tenant : null;
    }

    private static function node(mixed $livewire): ?Node
    {
        $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;

        return $record instanceof Node ? $record : null;
    }
}
