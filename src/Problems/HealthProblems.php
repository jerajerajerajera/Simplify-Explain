<?php

declare(strict_types=1);

namespace Simplify\Explain\Problems;

use App\Models\User;
use Simplify\Explain\Services\Explainer;
use Simplify\Explain\Services\HealthCheck;
use Simplify\Explain\Services\LogCheck;
use Simplify\Explain\Services\PanelLog;
use Throwable;

/** Pelican's health checks as errors Explain can explain: "simplify:health:<key>", for root admins only. */
final class HealthProblems
{
    private const PREFIX = 'simplify:health:';

    public static function id(string $key): string
    {
        return self::PREFIX.$key;
    }

    public function canExplainProblem(string $id, User $user): bool
    {
        return ($key = self::key($id)) !== null && Explainer::mayExplainLogs($user) && HealthCheck::find($key) !== null;
    }

    /** @return array{title: string, context: string, lines: list<string>}|null */
    public function explainProblem(string $id, User $user): ?array
    {
        if (! $this->canExplainProblem($id, $user) || ! ($check = HealthCheck::find((string) self::key($id)))) {
            return null;
        }
        try {
            $log = PanelLog::lines();
        } catch (Throwable) {
            $log = [];
        }

        return [
            'title' => __('simplify-explain::explain.health_heading', ['check' => self::label((string) self::key($id), $check['label'])]),
            'context' => HealthCheck::context().' '.Explainer::ASKED_FROM['panel'],
            'lines' => [...HealthCheck::lines($check), ...array_slice($log, -(LogCheck::LINES - 10))],
        ];
    }

    /** Pelican's own name for the check, in the panel's language. */
    public static function label(string $key, string $fallback): string
    {
        $name = 'admin/health.results.'.$key.'.label';

        return trans()->has($name) ? (string) trans($name) : $fallback;
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
