<?php

declare(strict_types=1);

namespace Simplify\Explain\Providers;

use App\Enums\HeaderActionPosition;
use App\Filament\Server\Pages\Console;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Simplify\Explain\ExplainAddon;
use Simplify\Explain\Filament\ExplainAction;
use Simplify\Explain\Livewire\ExplainPanel;

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
        Console::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::console());
    }
}
