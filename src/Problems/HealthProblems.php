<?php

declare(strict_types=1);

namespace Simplify\Explain\Problems;

use App\Models\User;
use Simplify\Explain\Services\Explainer;
use Simplify\Explain\Services\HealthCheck;
use Simplify\Explain\Services\LogCheck;
use Simplify\Explain\Services\PanelLog;
use Throwable;

/**
 * Pelican's health checks as errors Explain can explain, for root admins only: "simplify:health:<key>" for one check,
 * "simplify:health:all" for every check that fails or warns (Pelican's own Health page, without Simplify).
 */
final class HealthProblems
{
    private const PREFIX = 'simplify:health:';

    public const ALL = 'all';

    public static function id(string $key): string
    {
        return self::PREFIX.$key;
    }

    public function canExplainProblem(string $id, User $user): bool
    {
        return Explainer::mayExplainLogs($user) && self::checks($id) !== [];
    }

    /** @return array{title: string, context: string, lines: list<string>}|null */
    public function explainProblem(string $id, User $user): ?array
    {
        $checks = Explainer::mayExplainLogs($user) ? self::checks($id) : [];
        if ($checks === []) {
            return null;
        }
        try {
            $log = PanelLog::lines();
        } catch (Throwable) {
            $log = [];
        }
        $lines = [];
        foreach ($checks as $check) {
            $lines = [...$lines, ...HealthCheck::lines($check)];
        }
        $title = self::key($id) === self::ALL
            ? __('simplify-explain::explain.health_all_heading')
            : __('simplify-explain::explain.health_heading', ['check' => self::label((string) self::key($id), $checks[0]['label'])]);

        return [
            'title' => $title,
            'context' => HealthCheck::context().' '.Explainer::ASKED_FROM['panel'],
            'lines' => [...array_slice($lines, 0, 40), ...array_slice($log, -(LogCheck::LINES - min(40, count($lines))))],
        ];
    }

    /** Pelican's own name for the check, in the panel's language. */
    public static function label(string $key, string $fallback): string
    {
        $name = 'admin/health.results.'.$key.'.label';

        return trans()->has($name) ? (string) trans($name) : $fallback;
    }

    /**
     * The checks this id is about that need attention now; empty when none or when the id is not a health id.
     *
     * @return list<array{label: string, status: string, message: string, summary: string, meta: array<mixed>}>
     */
    private static function checks(string $id): array
    {
        $key = self::key($id);
        if ($key === null) {
            return [];
        }
        if ($key === self::ALL) {
            return HealthCheck::failing();
        }
        $check = HealthCheck::find($key);

        return $check !== null ? [$check] : [];
    }

    private static function key(string $id): ?string
    {
        if (! str_starts_with($id, self::PREFIX)) {
            return null;
        }
        $key = substr($id, strlen(self::PREFIX));

        return HealthCheck::validKey($key) ? $key : null;
    }
}
