<?php

declare(strict_types=1);

namespace Simplify\Explain\Console\Commands;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Console\Command;
use Simplify\Explain\Services\EmergencyAccess;
use Simplify\Explain\Services\Explainer;
use Simplify\Explain\Services\LogCheck;
use Simplify\Explain\Services\Settings;
use Throwable;

/**
 * Explain from the terminal, for when the panel website is down: reads the panel log, a Wings log or a server console
 * and prints the answer. Uses the saved key, or asks for one that is used once and not saved.
 */
final class CheckLog extends Command
{
    protected $signature = 'simplify-explain:check
        {--source=panel : panel, wings or server=<id> (a server id, short uuid or uuid)}
        {--node= : Node id for --source=wings (needed when there is more than one node)}
        {--lines=100 : At most this many log lines are sent (10 to 100)}
        {--url : Print a temporary address for the emergency page instead (works for 60 minutes, no sign-in needed)}';

    protected $description = 'Explain the panel log, a Wings log or a server console, also when the panel shows an error.';

    public function handle(): int
    {
        try {
            if ($this->option('url')) {
                return $this->address();
            }
            [$source, $server, $node] = $this->target();
            if ($source === null) {
                return self::FAILURE;
            }
            $key = $this->savedKey() ?? $this->askKey();
            if ($key === null) {
                return self::FAILURE;
            }
            $label = match ($source) {
                'panel' => 'Panel log',
                'wings' => 'Wings log of '.$node->name,
                default => 'Console of '.$server->name,
            };
            $this->line('Checking the '.lcfirst($label).'. This can take up to a minute.');
            $lines = max(10, min(LogCheck::LINES, (int) $this->option('lines')));
            $result = Explainer::ask($source, $server, $node, null, Explainer::ASKED_FROM['terminal'], $key, $lines);

            return $this->show($label, $result);
        } catch (Throwable $error) {
            report($error);
            $this->error('Could not finish: '.(LogCheck::sanitize([get_class($error).': '.$error->getMessage()])[0] ?? get_class($error)));

            return self::FAILURE;
        }
    }

    /**
     * The chosen log: [source, server, node], or [null, null, null] after printing why the choice does not work.
     *
     * @return array{0: ?string, 1: ?Server, 2: ?Node}
     */
    private function target(): array
    {
        $source = strtolower(trim((string) $this->option('source')));
        if ($source === 'panel') {
            return ['panel', null, null];
        }
        if ($source === 'wings') {
            $nodes = Node::query()->orderBy('id')->get();
            $wanted = $this->option('node');
            $node = $wanted !== null ? $nodes->firstWhere('id', (int) $wanted) : ($nodes->count() === 1 ? $nodes->first() : null);
            if ($node) {
                return ['wings', null, $node];
            }
            $this->error($wanted !== null ? 'There is no node with id '.$wanted.'.' : 'Choose a node with --node=<id>:');
            foreach ($nodes as $item) {
                $this->line('  '.$item->id.'  '.$item->name);
            }

            return [null, null, null];
        }
        if (preg_match('/^server[=:](.+)$/', $source, $match)) {
            $id = $match[1];
            $server = Server::query()
                ->where(fn ($query) => ctype_digit($id) ? $query->where('id', (int) $id) : $query->where('uuid_short', $id)->orWhere('uuid', $id))
                ->first();
            if ($server) {
                return ['console', $server, null];
            }
            $this->error('There is no server '.$id.'.');

            return [null, null, null];
        }
        $this->error('Use --source=panel, --source=wings or --source=server=<id>.');

        return [null, null, null];
    }

    /** The saved key; null when there is none or it cannot be read (no database, or APP_KEY changed). */
    private function savedKey(): ?string
    {
        try {
            return Settings::key();
        } catch (Throwable) {
            return null;
        }
    }

    private function askKey(): ?string
    {
        $this->warn('No API key is saved for Explain, or it can no longer be read (for example after APP_KEY changed).');
        $key = $this->input->isInteractive() ? trim((string) $this->secret('Paste an API key to use once (it is not saved)')) : '';
        if ($key === '') {
            $this->error('An API key is needed. Add one under Admin → Advanced → Explain, or run this command in a terminal to paste one.');

            return null;
        }

        return $key;
    }

    /** @param  array<string, mixed>  $result */
    private function show(string $label, array $result): int
    {
        if (! $result['ok']) {
            $this->error($result['error']);

            return self::FAILURE;
        }
        $this->newLine();
        $found = (bool) $result['found'];
        $this->line($found
            ? '<fg=yellow;options=bold>'.$label.': something needs attention</>'
            : '<fg=green;options=bold>'.$label.': everything seems in order</>');
        $this->line($result['summary']);
        if ($result['issues'] !== [] && ! $found) {
            $this->newLine();
            $this->line('<fg=gray>Noticed something?</>');
        }
        foreach ($result['issues'] as $number => $issue) {
            $this->newLine();
            $this->line('<options=bold>'.($number + 1).'. '.$issue['title'].'</>');
            if ($issue['cause'] !== '') {
                $this->line('   '.wordwrap($issue['cause'], 100, "\n   "));
            }
            foreach ($issue['steps'] as $step) {
                $this->line('   - '.wordwrap($step, 98, "\n     "));
            }
        }
        if ($result['model']) {
            $this->newLine();
            $this->line('<fg=gray>'.$result['lines'].($result['lines'] === 1 ? ' line' : ' lines').' checked by '.$result['model']
                .'. Addresses, e-mail addresses and passwords were removed first.</>');
        }

        return self::SUCCESS;
    }

    private function address(): int
    {
        $address = route('simplify-explain.emergency.access', ['token' => EmergencyAccess::issue()]);
        $this->info('Open this address in a browser. It works for '.EmergencyAccess::MINUTES.' minutes and replaces any earlier one:');
        $this->line($address);
        $this->line('<fg=gray>Anyone with this address can ask about the panel and Wings logs, so do not share it.</>');

        return self::SUCCESS;
    }
}
