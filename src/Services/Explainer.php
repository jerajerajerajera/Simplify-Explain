<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use App\Enums\SubuserPermission;
use App\Models\Node;
use App\Models\Server;
use Throwable;

/** Who may explain which log, and the question for each log. */
final class Explainer
{
    /** Where the question comes from, so the service knows whether the panel itself is up. */
    public const ASKED_FROM = [
        'panel' => 'The panel itself is up: an administrator is using it to ask this.',
        'emergency' => 'Asked from the emergency page, which works without the normal panel pages: the panel may be partly broken, for example showing error 500.',
        'terminal' => 'Asked from a terminal on the panel server: the panel website may be down.',
    ];

    /** Whether the user may explain the console of this server. */
    public static function mayExplainConsole(mixed $user, Server $server): bool
    {
        return Settings::availableFor($user) && $user->can(SubuserPermission::WebsocketConnect, $server);
    }

    /** The panel log and the Wings log can hold anyone's details, so only root admins may send them. */
    public static function mayExplainLogs(mixed $user): bool
    {
        return (bool) ($user?->isRootAdmin() && Settings::current()['has_key']);
    }

    /**
     * Explains the console of a server for the signed-in user.
     *
     * @return array<string, mixed>
     */
    public static function console(Server $server): array
    {
        $user = user();
        if (! self::mayExplainConsole($user, $server)) {
            return self::notAllowed();
        }

        return self::ask('console', $server, null, self::limitKey($user), '');
    }

    /**
     * Explains the panel's own log for a root admin.
     *
     * @return array<string, mixed>
     */
    public static function panel(): array
    {
        $user = user();
        if (! self::mayExplainLogs($user)) {
            return self::notAllowed();
        }

        return self::ask('panel', null, null, self::limitKey($user), self::ASKED_FROM['panel']);
    }

    /**
     * Explains the Wings log of a node for a root admin.
     *
     * @return array<string, mixed>
     */
    public static function wings(Node $node): array
    {
        $user = user();
        if (! self::mayExplainLogs($user)) {
            return self::notAllowed();
        }

        return self::ask('wings', null, $node, self::limitKey($user), self::ASKED_FROM['panel']);
    }

    /**
     * Reads a log and explains it, without checking who asks: the methods above, the emergency page and the
     * terminal command check that first. $source is console (of $server), panel or wings (of $node).
     *
     * @return array<string, mixed>
     */
    public static function ask(string $source, ?Server $server, ?Node $node, ?string $personal, string $asked, ?string $key = null, int $limit = LogCheck::LINES): array
    {
        try {
            $lines = match ($source) {
                'console' => ConsoleLog::lines($server),
                'panel' => PanelLog::lines(),
                'wings' => WingsLog::lines($node),
            };
        } catch (Throwable $error) {
            report($error);

            return ['ok' => false, 'error' => __('simplify-explain::explain.'.$source.'_unreadable')];
        }
        $context = match ($source) {
            'console' => ConsoleLog::context($server),
            'panel' => PanelLog::context(),
            'wings' => WingsLog::context($node),
        };
        $lines = array_slice($lines, -max(1, min(LogCheck::LINES, $limit)));

        return LogCheck::run($lines, trim($context.' '.$asked), $personal, __('simplify-explain::explain.'.$source.'_empty'), $key);
    }

    private static function limitKey(mixed $user): string
    {
        return 'simplify-explain.user.'.$user->id;
    }

    /** @return array{ok: false, error: string} */
    private static function notAllowed(): array
    {
        return ['ok' => false, 'error' => __('simplify-explain::explain.not_allowed')];
    }
}
