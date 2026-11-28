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

    /** Simplify Hosting's tickets (Tickets::draft), by name for the same reason. */
    private const TICKETS = 'Simplify\\Hosting\\Services\\Tickets';

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
            'console' => __('simplify-explain::explain.console_heading', ['server' => (string) (Server::find($this->serverId)->name ?? '')]),
            'panel' => __('simplify-explain::explain.panel_heading'),
            'wings' => __('simplify-explain::explain.wings_heading', ['node' => (string) (Node::find($this->nodeId)->name ?? '')]),
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

    /**
     * Whether "Open a ticket" starts a ticket in Simplify Hosting with the explanation filled in: for customers, with a
     * Hosting that has tickets and has them switched on.
     */
    public function ticketsReady(): bool
    {
        $user = user();
        if ($user === null || $user->isAdmin() || ! class_exists(self::TICKETS) || ! method_exists(self::TICKETS, 'draft')) {
            return false;
        }

        return ! method_exists(self::TICKETS, 'enabled') || (bool) call_user_func([self::TICKETS, 'enabled']);
    }

    /**
     * "Open a ticket" with Hosting's tickets: the explanation goes into a new ticket (Hosting keeps it in the session,
     * never in the address) and the customer goes to the form to check it and send it. Called on the click only, so
     * nothing is kept for someone who never presses it.
     */
    public function openTicket(): void
    {
        if (! $this->ticketsReady() || ! ($this->result['ok'] ?? false) || ! ($this->result['found'] ?? false)) {
            return;
        }
        $server = $this->source === 'console' ? Server::find($this->serverId) : null;
        if ($server !== null && ! Explainer::mayExplainConsole(user(), $server)) {
            $server = null;
        }
        $url = call_user_func([self::TICKETS, 'draft'], $server, $this->heading(), $this->ticketText());
        if (is_string($url) && $url !== '') {
            $this->redirect($url);
        }
    }

    /** The explanation as plain text for the ticket: the summary, then each problem with its cause and steps. */
    private function ticketText(): string
    {
        $lines = [];
        if (filled($this->result['summary'] ?? null)) {
            $lines[] = (string) $this->result['summary'];
            $lines[] = '';
        }
        foreach ($this->result['issues'] ?? [] as $issue) {
            $lines[] = (string) ($issue['title'] ?? '');
            $lines[] = (string) ($issue['cause'] ?? '');
            foreach (array_values($issue['steps'] ?? []) as $number => $step) {
                $lines[] = ($number + 1).'. '.$step;
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    public function render(): View
    {
        return view('simplify-explain::livewire.explain-panel');
    }
}
