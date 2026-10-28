<?php

declare(strict_types=1);

namespace Simplify\Explain\Filament;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Simplify\Explain\Services\Explainer;

/** The Explain button and its popup, for a server's console. */
final class ExplainAction
{
    public static function console(): Action
    {
        return Action::make('simplify-explain')
            ->label(__('simplify-explain::explain.button'))
            ->icon('tabler-message-question')
            ->color('gray')
            ->visible(fn () => ($server = self::server()) !== null && Explainer::mayExplainConsole(user(), $server))
            ->modalHeading(__('simplify-explain::explain.heading'))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalContent(fn () => view('simplify-explain::modal', ['source' => 'console', 'serverId' => self::server()?->id]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('simplify-explain::explain.close'));
    }

    private static function server(): ?Server
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Server ? $tenant : null;
    }
}
