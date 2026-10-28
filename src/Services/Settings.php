<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Simplify\Explain\Ai\Providers;
use Throwable;

/**
 * The chosen AI service, model and audience, and one encrypted API key per service. The keys never leave this class
 * except to be sent to their own service.
 */
final class Settings
{
    public const TABLE = 'simplify_explain_settings';

    /** Who sees the Explain button on a server's console. */
    public const AUDIENCES = ['everyone', 'admins'];

    /** @var array<string, mixed>|null */
    private static ?array $row = null;

    /**
     * @return array{provider: string, model: string, audience: string, has_key: bool}
     */
    public static function current(): array
    {
        $row = self::row();
        $provider = Providers::has($row['provider'] ?? null) ? $row['provider'] : Providers::default();

        return [
            'provider' => $provider,
            'model' => Providers::model($provider, $row['model'] ?? null),
            'audience' => in_array($row['audience'] ?? null, self::AUDIENCES, true) ? $row['audience'] : 'everyone',
            'has_key' => self::key($provider) !== null,
        ];
    }

    /**
     * Saves the choices. An empty key keeps the saved key of the chosen service; $removeKey forgets it.
     * Keys of the other services stay.
     *
     * @param  array<string, mixed>  $input
     */
    public static function save(array $input, bool $removeKey = false): void
    {
        $current = self::current();
        $provider = Providers::has($input['provider'] ?? null) ? $input['provider'] : $current['provider'];
        $keys = self::keys();
        $key = trim((string) ($input['key'] ?? ''));
        if ($removeKey) {
            unset($keys[$provider]);
        } elseif ($key !== '') {
            $keys[$provider] = $key;
        }
        DB::table(self::TABLE)->updateOrInsert(['id' => 1], [
            'provider' => $provider,
            'model' => Providers::model($provider, $input['model'] ?? null),
            'audience' => in_array($input['audience'] ?? null, self::AUDIENCES, true) ? $input['audience'] : $current['audience'],
            'api_keys' => $keys === [] ? null : Crypt::encryptString(json_encode($keys, JSON_THROW_ON_ERROR)),
            'updated_at' => now(),
        ]);
        self::$row = null;
    }

    /** The saved key of a service (the chosen one by default), or null. */
    public static function key(?string $provider = null): ?string
    {
        $provider ??= self::current()['provider'];
        $key = self::keys()[$provider] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /** Whether the user may explain a server's console. */
    public static function availableFor(mixed $user): bool
    {
        if (! $user) {
            return false;
        }
        $settings = self::current();

        return $settings['has_key'] && ($settings['audience'] === 'everyone' || $user->isAdmin());
    }

    /** @return array<string, string> */
    private static function keys(): array
    {
        $stored = self::row()['api_keys'] ?? null;
        if (! is_string($stored) || $stored === '') {
            return [];
        }
        try {
            $keys = json_decode(Crypt::decryptString($stored), true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            // The panel's APP_KEY changed: the key has to be entered again.
            return [];
        }

        return is_array($keys) ? array_filter($keys, 'is_string') : [];
    }

    /** @return array<string, mixed> */
    private static function row(): array
    {
        return self::$row ??= (array) (DB::table(self::TABLE)->find(1) ?? []);
    }
}
