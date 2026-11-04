<?php

declare(strict_types=1);

namespace Simplify\Explain\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Simplify\Explain\Filament\ExplainAction;

/**
 * The question button on a row of Pelican's Health page that needs attention. Simplify gives each such row a render
 * hook; this button brings its own popup.
 */
final class HealthCheckButton extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public string $check = '';

    #[Locked]
    public string $label = '';

    public function explainAction(): Action
    {
        return ExplainAction::health($this->check, $this->label)->name('explain');
    }

    public function render(): View
    {
        return view('simplify-explain::livewire.panel-log-button');
    }
}
