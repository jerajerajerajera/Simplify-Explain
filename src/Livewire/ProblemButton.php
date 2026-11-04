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
 * The question button right after an error anywhere in Simplify or an add-on. The view gives the render hook
 * simplify::problem.actions; this button brings its own popup.
 */
final class ProblemButton extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public string $problem = '';

    public function explainAction(): Action
    {
        return ExplainAction::problem($this->problem)->name('explain');
    }

    public function render(): View
    {
        return view('simplify-explain::livewire.problem-button');
    }
}
