<?php

declare(strict_types=1);

namespace Simplify\Explain\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Temporary addresses for the emergency page, made with `php artisan simplify-explain:check --url` for when signing in
 * to the panel fails. One address at a time: a new one replaces the old. Only a hash is kept, in a small file on the
 * local disk: not in the database or the cache, because those may be what is broken.
 */
final class EmergencyAccess
{
    public const FILE = 'simplify-explain/emergency-access.json';

    public const MINUTES = 60;

    /** A new token, valid for MINUTES minutes. */
    public static function issue(?int $now = null): string
    {
        $token = bin2hex(random_bytes(20));
        $data = ['hash' => hash('sha256', $token), 'expires' => ($now ?? time()) + self::MINUTES * 60];
        if (! Storage::disk('local')->put(self::FILE, json_encode($data, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Could not save the temporary address.');
        }

        return $token;
    }

    public static function valid(string $token, ?int $now = null): bool
    {
        $disk = Storage::disk('local');
        $data = $disk->exists(self::FILE) ? json_decode((string) $disk->get(self::FILE), true) : null;
        if (! is_array($data) || ! is_string($data['hash'] ?? null) || (int) ($data['expires'] ?? 0) < ($now ?? time())) {
            return false;
        }

        return preg_match('/^[0-9a-f]{40}$/', $token) === 1 && hash_equals($data['hash'], hash('sha256', $token));
    }
}
