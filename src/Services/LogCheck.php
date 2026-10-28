<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Simplify\Explain\Ai\Client;
use Simplify\Explain\Ai\Providers;
use Throwable;

/**
 * Asks the chosen AI service whether a log shows a real problem, and if so what it means and how to fix it.
 * Callers read the log and check who may ask; this class cleans the lines, limits how often people ask and
 * keeps answers for a few minutes.
 */
final class LogCheck
{
    /** Lines sent per question. Wings keeps at most 100 for its log endpoint. */
    public const LINES = 100;

    /** Questions per person per 10 minutes, and for the whole panel per day. */
    public const PER_USER = 6;

    public const PER_DAY = 200;

    public const SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['found', 'summary', 'issues'],
        'properties' => [
            'found' => [
                'type' => 'boolean',
                'description' => 'True only when the log shows a real problem: a crash, a failed start or install, something that stops a feature, a player or the server from working. False for a normal log, also when it has harmless warnings.',
            ],
            'summary' => [
                'type' => 'string',
                'description' => 'One or two short sentences in plain words: what went wrong, or when found is false, that everything seems in order.',
            ],
            'issues' => [
                'type' => 'array',
                'description' => 'At most four. Real problems first, then warnings someone might still want to look at. Empty when there is nothing worth mentioning.',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['title', 'cause', 'steps'],
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'A few words naming the problem. When found is false, phrase it as something the person may notice, for example "My panel feels slow" or "Players get disconnected".',
                        ],
                        'cause' => [
                            'type' => 'string',
                            'description' => 'What it means and the most likely cause, quoting the decisive log line or mod/plugin name when there is one. Say so when it is harmless.',
                        ],
                        'steps' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Up to five concrete things to try, most likely fix first. Empty when nothing needs to be done.',
                        ],
                    ],
                ],
            ],
        ],
    ];

    public const INSTRUCTIONS = 'You help people who run game servers (often Minecraft, sometimes other games) on a Pelican panel. '
        .'You get the last lines of a server\'s console, or for panel administrators the panel\'s own log or the Wings daemon log. '
        .'First check whether anything is actually wrong, then explain it for someone who is not a developer: short, friendly sentences, no jargon without a short explanation. '
        .'Point at the line or the mod, plugin, file or setting that causes a problem when the log shows it. '
        .'Suggest steps that can be done from a game panel: change a setting, remove or update a mod, restore a backup, give the server more memory, accept the EULA, check a file. '
        .'For panel and Wings logs, steps an administrator can take on the panel or node machine are fine too. '
        .'Never invent details that are not in the log; say so when the log is not conclusive. Parts like [hidden] were removed for privacy. '
        .'Most logs are fine. Servers print many warnings that are normal and need no action: deprecation notices, "Can\'t keep up!", missing optional mods or config defaults, slow chunk loading, unknown or bad packets from scanner bots, players disconnecting, '
        .'a single failed request or timeout that did not repeat (for example a few connection timeouts to Wings, such as cURL error 28, while everything else works), entries from hours or days ago that did not come back, and similar. '
        .'Set found to true only for a real problem: a crash, a failed start or install, an error that keeps repeating, or something that clearly stops the server, a feature or a player from working. '
        .'Otherwise set found to false and say that everything seems in order; list what the log does show as issues someone can open when they notice it (for example slow answers from Wings as "My panel feels slow"), and say when they are harmless.';

    /** Panel locale => the language the answer is written in. */
    public const LANGUAGES = [
        'ar' => 'Arabic', 'be' => 'Belarusian', 'bg' => 'Bulgarian', 'cs' => 'Czech', 'da' => 'Danish', 'de' => 'German',
        'el' => 'Greek', 'en' => 'English', 'es' => 'Spanish', 'fi' => 'Finnish', 'fr' => 'French', 'hu' => 'Hungarian',
        'id' => 'Indonesian', 'it' => 'Italian', 'ja' => 'Japanese', 'ko' => 'Korean', 'lt' => 'Lithuanian', 'nl' => 'Dutch',
        'no' => 'Norwegian', 'pl' => 'Polish', 'pt' => 'European Portuguese', 'pt_BR' => 'Brazilian Portuguese',
        'ro' => 'Romanian', 'ru' => 'Russian', 'sk' => 'Slovak', 'sr' => 'Serbian (Latin script)', 'sv' => 'Swedish',
        'tr' => 'Turkish', 'uk' => 'Ukrainian', 'vi' => 'Vietnamese', 'zh_CN' => 'Simplified Chinese', 'zh_TW' => 'Traditional Chinese',
    ];

    /**
     * Removes what should not leave the panel: colour codes, addresses, e-mail addresses, tokens and passwords.
     * Keeps the last LINES non-empty lines, each at most 600 characters.
     *
     * @param  iterable<mixed>  $lines
     * @return list<string>
     */
    public static function sanitize(iterable $lines): array
    {
        $patterns = [
            '/\b(password|passwd|secret|token|api[_-]?key|authorization)(\s*[:=]\s*)\S+/i' => '$1$2[hidden]',
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '[hidden email]',
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '[hidden ip]',
            '/\b(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{1,4}\b/i' => '[hidden ip]',
            '/\b(?=[A-Za-z0-9_\-]*\d)(?=[A-Za-z0-9_\-]*[A-Za-z])[A-Za-z0-9_\-]{32,}\b/' => '[hidden]',
        ];
        $clean = [];
        foreach ($lines as $line) {
            $line = preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]/', '', (string) $line) ?? '';
            $line = rtrim(preg_replace(array_keys($patterns), array_values($patterns), $line) ?? '');
            if ($line !== '') {
                $clean[] = mb_substr($line, 0, 600);
            }
        }

        return array_slice($clean, -self::LINES);
    }

    /** @param  list<string>  $lines */
    public static function question(array $lines, string $context): string
    {
        return ($context !== '' ? $context."\n" : '')
            ."Log, oldest line first:\n<log>\n".implode("\n", $lines)."\n</log>\n"
            .'Is everything working, or is something wrong here?';
    }

    /** The instructions with the language the answer should be in. */
    public static function instructions(string $locale): string
    {
        return self::INSTRUCTIONS.' Answer in '.(self::LANGUAGES[$locale] ?? self::LANGUAGES[explode('_', $locale)[0]] ?? 'English').'.';
    }

    /**
     * Reads the service's JSON answer; null when there is no usable answer.
     *
     * @return array{found: bool, summary: string, issues: list<array{title: string, cause: string, steps: list<string>}>}|null
     */
    public static function answer(?string $text): ?array
    {
        $data = $text === null ? null : json_decode($text, true);
        if (! is_array($data) || ! is_string($data['summary'] ?? null)) {
            return null;
        }
        $issues = [];
        foreach (array_slice((array) ($data['issues'] ?? []), 0, 4) as $issue) {
            if (! is_array($issue) || trim((string) ($issue['title'] ?? '')) === '') {
                continue;
            }
            $steps = array_map(fn ($step) => trim((string) $step), (array) ($issue['steps'] ?? []));
            $issues[] = [
                'title' => trim((string) $issue['title']),
                'cause' => trim((string) ($issue['cause'] ?? '')),
                'steps' => array_values(array_slice(array_filter($steps, fn (string $step) => $step !== ''), 0, 5)),
            ];
        }

        return ['found' => ($data['found'] ?? true) !== false, 'summary' => trim($data['summary']), 'issues' => $issues];
    }

    /**
     * Checks the lines with the saved key. $personal is the rate-limit key of the person asking (null in the terminal),
     * $context tells the service where the lines come from.
     *
     * @param  iterable<mixed>  $lines
     * @return array<string, mixed> ['ok' => true, 'found', 'summary', 'issues', 'lines', 'model'] or ['ok' => false, 'error']
     */
    public static function run(iterable $lines, string $context, ?string $personal, string $empty): array
    {
        $settings = Settings::current();
        $key = Settings::key();
        if ($key === null) {
            return ['ok' => false, 'error' => __('simplify-explain::explain.not_set_up')];
        }
        $lines = self::sanitize($lines);
        if ($lines === []) {
            return ['ok' => true, 'found' => false, 'summary' => $empty, 'issues' => [], 'lines' => 0, 'model' => null];
        }
        $provider = Providers::get($settings['provider']);
        $locale = app()->getLocale();
        $cacheKey = 'simplify-explain.answer.'.sha1($settings['provider'].$settings['model'].$locale.$context.implode("\n", $lines));
        $cached = self::guarded(fn () => Cache::get($cacheKey));
        if (is_array($cached)) {
            return ['ok' => true, ...$cached];
        }
        if ($personal !== null && self::guarded(fn () => RateLimiter::tooManyAttempts($personal, self::PER_USER), false)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($personal) / 60));

            return ['ok' => false, 'error' => trans_choice('simplify-explain::explain.too_many', $minutes, ['minutes' => $minutes])];
        }
        if (self::guarded(fn () => RateLimiter::tooManyAttempts('simplify-explain.panel', self::PER_DAY), false)) {
            return ['ok' => false, 'error' => __('simplify-explain::explain.daily_limit', ['limit' => self::PER_DAY])];
        }
        self::guarded(function () use ($personal): void {
            if ($personal !== null) {
                RateLimiter::hit($personal, 600);
            }
            RateLimiter::hit('simplify-explain.panel', 86400);
        });
        // The time lets the service see that a warning from hours ago is not a problem now.
        $context = trim($context."\nCurrent time on the panel: ".date('Y-m-d H:i:s T'));
        $request = $provider::request($key, $settings['model'], self::instructions($locale), self::SCHEMA, self::question($lines, $context));
        $result = Client::send($provider, $request);
        if (! $result['ok']) {
            return $result;
        }
        $answer = self::answer($provider::text($result['body']));
        $model = $provider::modelName($settings['model']);
        if ($answer === null) {
            return ['ok' => false, 'error' => __('simplify-explain::explain.no_answer', ['model' => $model])];
        }
        $answer += ['lines' => count($lines), 'model' => $model];
        self::guarded(fn () => Cache::put($cacheKey, $answer, now()->addMinutes(10)));

        return ['ok' => true, ...$answer];
    }

    /** Runs $callback; when the cache store is broken (a half-working panel) it returns $fallback instead of failing. */
    private static function guarded(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $error) {
            report($error);

            return $fallback;
        }
    }
}
