<?php

declare(strict_types=1);

namespace Simplify\Explain\Providers;

use App\Enums\HeaderActionPosition;
use App\Filament\Admin\Pages\ListLogs;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Server\Pages\Console;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Simplify\Explain\ExplainAddon;
use Simplify\Explain\Filament\ExplainAction;
use Simplify\Explain\Livewire\ExplainPanel;
use Simplify\Explain\Livewire\PanelLogButton;
use Simplify\Explain\Services\Explainer;

final class ExplainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Simplify reads this tag to list its add-ons. Without Simplify nothing reads it and ExplainAddon is never loaded.
        $this->app->tag([ExplainAddon::class], 'simplify.addons');
    }

    public function boot(): void
    {
        Livewire::component('simplify-explain-panel', ExplainPanel::class);
        Livewire::component('simplify-explain-panel-log-button', PanelLogButton::class);

        Console::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::console());
        EditNode::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::wingsLog());
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
            fn () => Explainer::mayExplainLogs(user()) ? Blade::render('@livewire(\'simplify-explain-panel-log-button\')') : '',
            scopes: ListLogs::class,
        );
    }
}
