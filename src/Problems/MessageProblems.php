<?php

declare(strict_types=1);

namespace Simplify\Explain\Problems;

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Simplify\Explain\Services\ConsoleLog;
use Simplify\Explain\Services\Explainer;
use Simplify\Explain\Services\LogCheck;
use Simplify\Explain\Services\PanelLog;
use Throwable;

/**
 * Pelican's red messages (Filament notifications with the status "danger") as errors Explain can explain:
 * "pelican:message:<token>". The message is kept on the panel for a while when it is sent, for the person it was sent
 * to, so the click never takes the text from the browser.
 */
final class MessageProblems
{
    private const PREFIX = 'pelican:message:';

    /** How long a message can be explained after it was shown. */
    private const MINUTES = 30;

    /** The longest message text that is kept. */
    private const MAX_TEXT = 1000;

    /** Keeps a red message for the signed-in user and returns its id, or null when there is nothing to keep. */
    public static function remember(string $title, string $body, ?Server $server): ?string
    {
        $user = user();
        $title = self::clean($title);
        $body = self::clean($body);
        if (! $user || ($title === '' && $body === '')) {
            return null;
        }
        $token = Str::random(32);
        Cache::put(self::cacheKey($token), [
            'user' => (int) $user->getKey(),
            'title' => $title,
            'body' => $body,
            'server' => $server?->getKey(),
        ], now()->addMinutes(self::MINUTES));

        return self::PREFIX.$token;
    }

    public function canExplainProblem(string $id, User $user): bool
    {
        return $this->message($id, $user) !== null;
    }

    /** @return array{title: string, context: string, lines: list<string>}|null */
    public function explainProblem(string $id, User $user): ?array
    {
        if (! ($message = $this->message($id, $user))) {
            return null;
        }
        $lines = [];
        if ($message['title'] !== '') {
            $lines[] = 'Message: '.$message['title'];
        }
        if ($message['body'] !== '') {
            $lines[] = 'Details: '.$message['body'];
        }
        $context = 'The Pelican panel showed this error message to the user.';
        $server = $message['server'] !== null ? Server::find($message['server']) : null;
        if ($server && Explainer::mayExplainConsole($user, $server)) {
            try {
                $lines = [...$lines, ...array_slice(ConsoleLog::lines($server), -(LogCheck::LINES - 20))];
                $context .= ' It happened on a server; its recent console lines follow. '.ConsoleLog::context($server);
            } catch (Throwable) {
                // Wings is not reachable: the message alone.
            }
        } elseif (Explainer::mayExplainLogs($user)) {
            try {
                $lines = [...$lines, ...array_slice(PanelLog::lines(), -(LogCheck::LINES - 20))];
                $context .= ' The recent warnings and errors of the panel log follow, newest last. '.Explainer::ASKED_FROM['panel'];
            } catch (Throwable) {
                // The log cannot be read: the message alone.
            }
        }

        return [
            'title' => $message['title'] !== '' ? $message['title'] : Str::limit($message['body'], 80),
            'context' => $context.' Explain what went wrong and how the user fixes it.',
            'lines' => $lines,
        ];
    }

    /** @return array{user: int, title: string, body: string, server: int|string|null}|null */
    private function message(string $id, User $user): ?array
    {
        if (! str_starts_with($id, self::PREFIX)) {
            return null;
        }
        $token = substr($id, strlen(self::PREFIX));
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return null;
        }
        $message = Cache::get(self::cacheKey($token));
        if (! is_array($message) || ($message['user'] ?? null) !== (int) $user->getKey()) {
            return null;
        }

        return [
            'user' => (int) $message['user'],
            'title' => (string) ($message['title'] ?? ''),
            'body' => (string) ($message['body'] ?? ''),
            'server' => $message['server'] ?? null,
        ];
    }

    private static function cacheKey(string $token): string
    {
        return 'simplify-explain:message:'.$token;
    }

    /** The text without HTML and extra white space. */
    private static function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), self::MAX_TEXT, '…');
    }
}
