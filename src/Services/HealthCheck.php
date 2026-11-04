<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use Closure;
use Spatie\Health\ResultStores\ResultStore;

/**
 * One of Pelican's health checks, read from the results Pelican stored when it last ran them. Never runs a check and
 * never takes the result from the browser, so only what the panel itself saw is sent.
 */
final class HealthCheck
{
    /** Returns the stored results instead of Pelican's result store. Tests set it. */
    public static ?Closure $stored = null;

    /** The statuses that get a question button. */
    public const NEEDS_ATTENTION = ['failed', 'crashed', 'warning'];

    /** How Simplify names a check in its render hook: the label, lower case, without spaces ("Node Versions" => nodeversions). */
    public static function key(string $label): string
    {
        return preg_replace('/\s+/', '', mb_strtolower($label)) ?? '';
    }

    /** A key as Simplify passes it; anything else is ignored. */
    public static function validKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z0-9_-]{1,64}$/', $key);
    }

    /**
     * The stored result of a check that needs attention, or null when there is none.
     *
     * @return array{label: string, status: string, message: string, summary: string, meta: array<mixed>}|null
     */
    public static function find(string $key): ?array
    {
        if (! self::validKey($key)) {
            return null;
        }
        foreach (self::stored() as $result) {
            $status = strtolower((string) $result->status);
            if (self::key((string) $result->label) === $key && in_array($status, self::NEEDS_ATTENTION, true)) {
                return [
                    'label' => (string) $result->label,
                    'status' => $status,
                    'message' => trim((string) $result->notificationMessage),
                    'summary' => trim((string) $result->shortSummary),
                    'meta' => (array) $result->meta,
                ];
            }
        }

        return null;
    }

    /** @return iterable<object> Pelican's stored check results, each with label, status, notificationMessage, shortSummary and meta. */
    private static function stored(): iterable
    {
        if (self::$stored !== null) {
            return (self::$stored)();
        }
        if (! interface_exists(ResultStore::class)) {
            return [];
        }

        return app(ResultStore::class)->latestResults()?->storedCheckResults ?? [];
    }

    /**
     * The result as lines for the question, before the panel log.
     *
     * @param  array{label: string, status: string, message: string, summary: string, meta: array<mixed>}  $check
     * @return list<string>
     */
    public static function lines(array $check): array
    {
        $lines = ['Health check: '.$check['label'], 'Status: '.$check['status']];
        if ($check['message'] !== '') {
            $lines[] = 'Message: '.$check['message'];
        }
        if ($check['summary'] !== '' && $check['summary'] !== $check['message']) {
            $lines[] = 'Summary: '.$check['summary'];
        }
        if ($check['meta'] !== []) {
            $lines[] = 'Details: '.json_encode($check['meta'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return $lines;
    }

    public static function context(): string
    {
        return 'One of the Pelican panel\'s health checks (spatie/laravel-health) failed or warns. Its result comes first, '
            .'then the recent warnings and errors of the panel log, newest last. Explain why this check fails and how an '
            .'administrator fixes it on the panel machine, for example the queue worker service, the cron job that runs '
            .'the scheduler, the cache, the database, the disk or an outdated Pelican or Wings.';
    }
}
