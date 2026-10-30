<?php

declare(strict_types=1);

namespace Simplify\Explain\Livewire;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Simplify\Core\Support\PanelSettings;
use Simplify\Explain\Services\Explainer;
use Simplify\Hosting\Providers\HostingServiceProvider;

/** The content of the Explain popup: checks the log once it opens and shows the answer. */
final class ExplainPanel extends Component
{
    /** console (of serverId), panel, or wings (of nodeId). */
    #[Locked]
    public string $source = 'console';

    #[Locked]
    public ?int $serverId = null;

    #[Locked]
    public ?int $nodeId = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function check(): void
    {
        $this->result = match ($this->source) {
            'console' => Explainer::console(Server::findOrFail($this->serverId)),
            'panel' => Explainer::panel(),
            'wings' => Explainer::wings(Node::findOrFail($this->nodeId)),
            default => ['ok' => false, 'error' => __('simplify-explain::explain.not_allowed')],
        };
    }

    /**
     * For customers of a host: the panel's support link from Simplify's settings, under a problem the answer found.
     * Only with Simplify Hosting loaded; a panel without customers has no use for a ticket.
     */
    public function supportUrl(): ?string
    {
        if (user()?->isAdmin() || ! class_exists(PanelSettings::class) || ! app()->providerIsLoaded(HostingServiceProvider::class)) {
            return null;
        }
        $url = PanelSettings::get('support_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function render(): View
    {
        return view('simplify-explain::livewire.explain-panel');
    }
}
