<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use App\Enums\SubuserPermission;
use App\Models\Server;
use Throwable;

/** Who may explain which log, and the question for each log. */
final class Explainer
{
    /** Whether the user may explain the console of this server. */
    public static function mayExplainConsole(mixed $user, Server $server): bool
    {
        return Settings::availableFor($user) && $user->can(SubuserPermission::WebsocketConnect, $server);
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
            return ['ok' => false, 'error' => __('simplify-explain::explain.not_allowed')];
        }
        try {
            $lines = ConsoleLog::lines($server);
        } catch (Throwable $error) {
            report($error);

            return ['ok' => false, 'error' => __('simplify-explain::explain.console_unreadable')];
        }

        return LogCheck::run($lines, ConsoleLog::context($server), 'simplify-explain.user.'.$user->id, __('simplify-explain::explain.console_empty'));
    }
}
