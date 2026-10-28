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
    /** Says where the question comes from, so the service knows whether the panel itself is up. */
    private const ASKED_FROM_PANEL = 'The panel itself is up: an administrator is using it to ask this.';

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
        try {
            $lines = ConsoleLog::lines($server);
        } catch (Throwable $error) {
            report($error);

            return ['ok' => false, 'error' => __('simplify-explain::explain.console_unreadable')];
        }

        return LogCheck::run($lines, ConsoleLog::context($server), self::limitKey($user), __('simplify-explain::explain.console_empty'));
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
        try {
            $lines = PanelLog::lines();
        } catch (Throwable $error) {
            report($error);

            return ['ok' => false, 'error' => __('simplify-explain::explain.panel_unreadable')];
        }

        return LogCheck::run($lines, PanelLog::context().' '.self::ASKED_FROM_PANEL, self::limitKey($user), __('simplify-explain::explain.panel_empty'));
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
        try {
            $lines = WingsLog::lines($node);
        } catch (Throwable $error) {
            report($error);

            return ['ok' => false, 'error' => __('simplify-explain::explain.wings_unreadable')];
        }

        return LogCheck::run($lines, WingsLog::context($node).' '.self::ASKED_FROM_PANEL, self::limitKey($user), __('simplify-explain::explain.wings_empty'));
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
