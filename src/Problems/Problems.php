<?php

declare(strict_types=1);

namespace Simplify\Explain\Problems;

use Filament\Actions\Action;
use Simplify\Explain\Services\Settings;
use Throwable;

/**
 * Errors shown anywhere in Simplify or its add-ons that Explain can explain. A view marks an error with the render hook
 * simplify::problem.actions and an id like "simplify-eggupdates:test:42"; the plugin that owns the error tags a source
 * 'simplify.problems' that says who may see it and which log belongs to it. Sources are duck typed, so no plugin needs
 * Explain to offer one.
 */
final class Problems
{
    public const TAG = 'simplify.problems';

    /**
     * An "Explain" button for a Filament notification about this error, or null when this user cannot have it
     * explained. Notifications strip the hook's HTML, so call this instead:
     * class_exists(Problems::class) ? Problems::notificationAction($id) : null.
     */
    public static function notificationAction(string $id): ?Action
    {
        try {
            if (! Settings::availableFor(user()) || self::sourceFor($id, user()) === null) {
                return null;
            }

            return Action::make('simplify-explain-problem')
                ->label(__('simplify-explain::explain.button'))
                ->icon('tabler-message-question')
                ->color('gray')
                ->dispatch('simplify-explain-problem', ['problem' => $id])
                ->close();
        } catch (Throwable $error) {
            report($error);

            return null;
        }
    }

    /** "<plugin>:<kind>:<ref>", 2 to 5 parts of letters, digits, dot, dash or underscore. */
    public static function validId(string $id): bool
    {
        return strlen($id) <= 128 && (bool) preg_match('/^[A-Za-z0-9_.-]+(:[A-Za-z0-9_.-]+){1,4}$/', $id);
    }

    /** A template id holds __ID__, which the page replaces in the browser; it is never explained itself. */
    public static function isTemplate(string $id): bool
    {
        return str_contains($id, '__ID__');
    }

    /** The placeholder for a whole id, for lists whose rows come from several plugins (Simplify's bell). */
    public const TEMPLATE = '__ID__';

    /** The id in a render hook's scopes ("simplify-problem:<id>", or "simplify-problem:__ID__" for a whole-id template), or null. */
    public static function fromScopes(array $scopes): ?string
    {
        foreach ($scopes as $scope) {
            if (is_string($scope) && str_starts_with($scope, 'simplify-problem:')) {
                $id = substr($scope, strlen('simplify-problem:'));

                return $id === self::TEMPLATE || self::validId($id) ? $id : null;
            }
        }

        return null;
    }

    /** The first source that may explain this error to this user, or null. */
    public static function sourceFor(string $id, mixed $user): ?object
    {
        if (! $user || ! self::validId($id) || self::isTemplate($id)) {
            return null;
        }
        foreach (self::sources() as $source) {
            try {
                if (method_exists($source, 'canExplainProblem') && method_exists($source, 'explainProblem') && $source->canExplainProblem($id, $user) === true) {
                    return $source;
                }
            } catch (Throwable $error) {
                report($error);
            }
        }

        return null;
    }

    /**
     * What the source gives for this error after a click, cleaned up, or null.
     *
     * @return array{title: string, context: string, lines: list<string>}|null
     */
    public static function details(string $id, mixed $user): ?array
    {
        $source = self::sourceFor($id, $user);
        $data = $source?->explainProblem($id, $user);
        if (! is_array($data) || ! is_array($data['lines'] ?? null)) {
            return null;
        }

        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'context' => trim((string) ($data['context'] ?? '')),
            'lines' => array_values(array_map(fn ($line) => (string) $line, array_filter($data['lines'], 'is_scalar'))),
        ];
    }

    /** @return list<object> Explain's own sources first, then those tagged by other plugins. */
    private static function sources(): array
    {
        $sources = [app(HealthProblems::class)];
        try {
            foreach (app()->tagged(self::TAG) as $source) {
                if (is_object($source)) {
                    $sources[] = $source;
                }
            }
        } catch (Throwable $error) {
            report($error);
        }

        return $sources;
    }
}
