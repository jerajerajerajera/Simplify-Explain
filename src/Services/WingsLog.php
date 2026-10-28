<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use App\Models\Node;
use App\Repositories\Daemon\DaemonSystemRepository;

/** The log of the Wings daemon on a node, from its diagnostics report. */
final class WingsLog
{
    /** @return list<string> */
    public static function lines(Node $node): array
    {
        $report = app(DaemonSystemRepository::class)
            ->setNode($node)
            ->getDiagnostics(LogCheck::LINES, false, true)
            ->throw()
            ->body();

        return self::fromReport((string) $report);
    }

    /**
     * The log part of a diagnostics report: everything after its logs heading, last lines only.
     *
     * @return list<string>
     */
    public static function fromReport(string $report): array
    {
        $lines = preg_split('/\r?\n/', $report) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^\W*(latest\s+)?(wings\s+)?logs?\b/i', trim($line))) {
                $lines = array_slice($lines, $index + 1);
                break;
            }
        }
        $lines = array_filter($lines, fn (string $line) => trim($line, " \t=-") !== '');

        return array_slice(array_values($lines), -LogCheck::LINES);
    }

    public static function context(Node $node): string
    {
        return 'Wings daemon log of node "'.$node->name.'"';
    }
}
