<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use SplFileObject;

/** Warnings and errors from the panel's own Laravel log. */
final class PanelLog
{
    /** The most read from the end of the newest log file. */
    private const TAIL = 262144;

    /** Glob of the log files; storage/logs/*.log when null. Tests point it elsewhere. */
    public static ?string $files = null;

    /** @return list<string> */
    public static function lines(): array
    {
        return self::entries(self::tail());
    }

    /** The end of the newest log file. */
    public static function tail(): string
    {
        $files = glob(self::$files ?? storage_path('logs').'/*.log') ?: [];
        if ($files === []) {
            return '';
        }
        usort($files, fn (string $a, string $b) => [filemtime($b), $b] <=> [filemtime($a), $a]);
        $file = new SplFileObject($files[0], 'r');
        $size = $file->getSize();
        if ($size <= 0) {
            return '';
        }
        $file->fseek(max(0, $size - self::TAIL));

        return (string) $file->fread(min($size, self::TAIL));
    }

    /**
     * The last eight warnings and errors of a Laravel log, each its message and first stack frames.
     *
     * @return list<string>
     */
    public static function entries(string $text): array
    {
        $entries = preg_split('/\r?\n(?=\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $text) ?: [];
        $wanted = array_values(array_filter(
            $entries,
            fn (string $entry) => (bool) preg_match('/^\[[^\]]+\] [\w-]+\.(WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', ltrim($entry)),
        ));
        $lines = [];
        foreach (array_slice($wanted, -8) as $entry) {
            array_push($lines, ...array_slice(preg_split('/\r?\n/', trim($entry)) ?: [], 0, 12));
        }

        return $lines;
    }

    public static function context(): string
    {
        return 'Pelican panel log (Laravel), warnings and errors only, newest last.';
    }
}
