<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;

/** The last lines of a server's console, read from Wings: the same log the console shows. */
final class ConsoleLog
{
    private const INSTALLING = ['installing', 'install_failed', 'reinstall_failed'];

    /** @return list<string> */
    public static function lines(Server $server): array
    {
        $repository = app(DaemonServerRepository::class)->setServer($server);
        // While installing, or after a failed install, the install log tells what went wrong.
        if (in_array($server->status?->value, self::INSTALLING, true)) {
            return array_slice(preg_split('/\r?\n/', (string) $repository->getInstallLogs()) ?: [], -LogCheck::LINES);
        }
        $lines = $repository->getHttpClient()
            ->timeout(10)
            ->get('/api/servers/'.$server->uuid.'/logs', ['size' => LogCheck::LINES])
            ->throw()
            ->json('data');

        return array_values(array_filter((array) $lines, 'is_string'));
    }

    /** What the service is told about the lines. */
    public static function context(Server $server): string
    {
        return $server->egg?->name ? 'Game or software: '.$server->egg->name : '';
    }
}
