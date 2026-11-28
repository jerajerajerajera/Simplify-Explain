<?php

// Stands in for Simplify Hosting's tickets (Tickets::draft) when Simplify Hosting is not installed in the test panel.

namespace Simplify\Hosting\Services;

use App\Models\Server;

final class Tickets
{
    public static bool $on = true;

    /** @var list<array{server: ?int, subject: string, text: string}> */
    public static array $drafts = [];

    public static function enabled(): bool
    {
        return self::$on;
    }

    public static function draft(?Server $server, string $subject, string $text): ?string
    {
        if (! self::$on) {
            return null;
        }
        self::$drafts[] = ['server' => $server?->id, 'subject' => $subject, 'text' => $text];

        return 'https://panel.example.com/smoke-new-ticket';
    }
}
