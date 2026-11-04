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

/** The content of the Explain popup: checks the log once it opens and shows the answer. */
final class ExplainPanel extends Component
{
    /** Simplify Hosting's provider, by name: Hosting is optional, so its class may not exist. */
    private const HOSTING = 'Simplify\\Hosting\\Providers\\HostingServiceProvider';

    /** console (of serverId), panel, wings (of nodeId) or problem (an error a plugin marked). */
    #[Locked]
    public string $source = 'console';

    #[Locked]
    public ?int $serverId = null;

    #[Locked]
    public ?int $nodeId = null;

    /** The error's id, such as simplify-eggupdates:test:42. */
    #[Locked]
    public ?string $problem = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    /** The window's title: what is explained. */
    public function heading(): string
    {
        $title = (string) ($this->result['title'] ?? '');
        if ($title !== '') {
            return $title;
        }

        return match ($this->source) {
            'console' => __('simplify-explain::explain.console_heading', ['server' => (string) (Server::find($this->serverId)?->name ?? '')]),
            'panel' => __('simplify-explain::explain.panel_heading'),
            'wings' => __('simplify-explain::explain.wings_heading', ['node' => (string) (Node::find($this->nodeId)?->name ?? '')]),
            default => __('simplify-explain::explain.heading'),
        };
    }

    /** The window's subtitle: where the lines come from. */
    public function from(): string
    {
        return __('simplify-explain::explain.'.(in_array($this->source, ['console', 'panel', 'wings'], true) ? $this->source : 'problem').'_from');
    }

    public function check(): void
    {
        $this->result = match ($this->source) {
            'console' => Explainer::console(Server::findOrFail($this->serverId)),
            'panel' => Explainer::panel(),
            'wings' => Explainer::wings(Node::findOrFail($this->nodeId)),
            'problem' => Explainer::problem((string) $this->problem),
            default => ['ok' => false, 'error' => __('simplify-explain::explain.not_allowed')],
        };
    }

    /**
     * For customers of a host: the panel's support link from Simplify's settings, under a problem the answer found.
     * Only with Simplify Hosting loaded; a panel without customers has no use for a ticket.
     */
    public function supportUrl(): ?string
    {
        if (user()?->isAdmin() || ! class_exists(PanelSettings::class) || ! app()->providerIsLoaded(self::HOSTING)) {
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
