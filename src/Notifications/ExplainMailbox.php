<?php

declare(strict_types=1);

namespace Simplify\Explain\Notifications;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Contracts\Support\Htmlable;
use Simplify\Explain\Problems\MessageProblems;
use Simplify\Explain\Problems\Problems;
use Simplify\Explain\Services\Settings;
use Throwable;

/**
 * Filament's mailbox for the messages of this request, with Explain's link added to every red message (status
 * "danger") for people who may use Explain. Works with or without Simplify; a message that already carries
 * the action from Problems::notificationAction() keeps just that one.
 */
class ExplainMailbox extends NotificationMailbox
{
    /** @param array<string, mixed> $notification */
    public function push(array $notification): void
    {
        parent::push(self::withExplain($notification));
    }

    /**
     * @param  array<string, mixed>  $notification
     * @return array<string, mixed>
     */
    public static function withExplain(array $notification): array
    {
        try {
            if (($notification['status'] ?? null) !== 'danger' || self::hasExplain($notification) || ! Settings::availableFor(user())) {
                return $notification;
            }
            $id = MessageProblems::remember(self::text($notification['title'] ?? ''), self::text($notification['body'] ?? ''), self::server());
            $action = $id !== null ? Problems::notificationAction($id) : null;
            if ($action !== null) {
                $notification['actions'] = [...(array) ($notification['actions'] ?? []), $action->toArray()];
            }
        } catch (Throwable $error) {
            report($error);
        }

        return $notification;
    }

    /** @param array<string, mixed> $notification */
    private static function hasExplain(array $notification): bool
    {
        foreach ((array) ($notification['actions'] ?? []) as $action) {
            if (is_array($action) && ($action['name'] ?? null) === Problems::NOTIFICATION_ACTION) {
                return true;
            }
        }

        return false;
    }

    private static function text(mixed $value): string
    {
        return $value instanceof Htmlable ? $value->toHtml() : (is_scalar($value) ? (string) $value : '');
    }

    /** The server whose page sent the message, if any. */
    private static function server(): ?Server
    {
        try {
            $tenant = Filament::getTenant();
        } catch (Throwable) {
            return null;
        }

        return $tenant instanceof Server ? $tenant : null;
    }
}
