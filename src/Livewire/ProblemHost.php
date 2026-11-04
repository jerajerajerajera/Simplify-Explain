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
use Simplify\Explain\Problems\Problems;

/**
 * Once per page: opens the Explain popup for a question button after an error. The buttons themselves are plain HTML
 * with data-simplify-explain="<id>", so a page may also draw them in the browser from a template.
 */
final class ProblemHost extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** A click on a question button. Who may see the error is checked when the popup asks its source. */
    public function open(string $problem): void
    {
        if (Problems::validId($problem) && ! Problems::isTemplate($problem)) {
            $this->mountAction('explain', ['problem' => $problem]);
        }
    }

    public function explainAction(): Action
    {
        return ExplainAction::problem()->name('explain');
    }

    public function render(): View
    {
        return view('simplify-explain::livewire.problem-host');
    }
}
