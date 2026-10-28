<?php

declare(strict_types=1);

namespace Simplify\Explain\Livewire;

use App\Models\Server;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Simplify\Explain\Services\Explainer;

/** The content of the Explain popup: checks the log once it opens and shows the answer. */
final class ExplainPanel extends Component
{
    #[Locked]
    public string $source = 'console';

    #[Locked]
    public ?int $serverId = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function check(): void
    {
        $this->result = match ($this->source) {
            'console' => Explainer::console(Server::findOrFail($this->serverId)),
            default => ['ok' => false, 'error' => __('simplify-explain::explain.not_allowed')],
        };
    }

    public function render(): View
    {
        return view('simplify-explain::livewire.explain-panel');
    }
}
