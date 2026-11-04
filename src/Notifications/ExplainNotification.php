<?php

declare(strict_types=1);

namespace Simplify\Explain\Notifications;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Simplify\Explain\Problems\MessageProblems;
use Simplify\Explain\Problems\Problems;
use Simplify\Explain\Services\Settings;
use Throwable;

/**
 * Filament's notification with Explain's action added to every red message (status "danger") when it is sent, for
 * people who may use Explain. Works with or without Simplify and on every Filament version Pelican uses, because
 * Notification::make() takes its class from the container. A message that already carries the action from
 * Problems::notificationAction() keeps just that one.
 */
class ExplainNotification extends Notification
{
    public function send(): static
    {
        try {
            if ($this->getStatus() === 'danger' && ! $this->hasExplain() && Settings::availableFor(user())) {
                $id = MessageProblems::remember(self::text($this->getTitle()), self::text($this->getBody()), self::server());
                $action = $id !== null ? Problems::notificationAction($id) : null;
                if ($action !== null) {
                    $this->actions([...$this->getActions(), $action]);
                }
            }
        } catch (Throwable $error) {
            report($error);
        }

        return parent::send();
    }

    private function hasExplain(): bool
    {
        foreach ($this->getActions() as $action) {
            if ($action instanceof Action && $action->getName() === Problems::NOTIFICATION_ACTION) {
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
