<?php

declare(strict_types=1);

namespace Simplify\Explain\Filament;

use App\Models\Node;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Simplify\Explain\Services\Explainer;

/** The Explain buttons and their popup: for a server's console, the panel log, a node's Wings log and any error that a plugin marks. */
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

    /** The popup for an error a plugin marked; ProblemHost mounts it with the error's id as argument. */
    public static function problem(): Action
    {
        return self::make('simplify-explain-problem', __('simplify-explain::explain.button'))
            ->modalContent(fn (array $arguments) => view('simplify-explain::modal', ['source' => 'problem', 'problem' => (string) ($arguments['problem'] ?? '')]));
    }

    private static function make(string $name, string $label): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('tabler-message-question')
            ->color('gray')
            // The window's own header (title, where the lines come from, Check again) is in the panel: Simplify's header model.
            ->modalHeading('')
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
