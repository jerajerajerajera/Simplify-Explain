<?php

declare(strict_types=1);

namespace Simplify\Explain\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Simplify\Explain\Filament\ExplainAction;

/**
 * "Explain panel log" above Pelican's log list. That page has no header actions to add to, so this button is placed
 * with a render hook and brings its own popup.
 */
final class PanelLogButton extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function explainAction(): Action
    {
        return ExplainAction::panelLog()->name('explain');
    }

    public function render(): View
    {
        return view('simplify-explain::livewire.panel-log-button');
    }
}
