<?php

// Stands in for Simplify's panel settings when Simplify is not installed in the test panel.

namespace Simplify\Core\Support;

final class PanelSettings
{
    /** @var array<string, mixed> */
    public static array $values = [];

    public static function get(string $key): mixed
    {
        return self::$values[$key] ?? null;
    }
}
