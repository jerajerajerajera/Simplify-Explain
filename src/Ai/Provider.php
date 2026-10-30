<?php

declare(strict_types=1);

namespace Simplify\Explain\Ai;

/**
 * One AI service that can check a log: how to ask it, how to read its answer and how to put its errors in plain words.
 * Every request is made with the administrator's own key, and only to the service's own API host.
 */
abstract class Provider
{
    /** The company, used in messages, such as "Anthropic". */
    abstract public static function company(): string;

    /** The choice on the settings page, such as "Claude by Anthropic". */
    abstract public static function label(): string;

    /** True for a service on the administrator's own server: its "key" is the address and any model can be typed in. */
    public static function selfHosted(): bool
    {
        return false;
    }

    /**
     * Model id => price tier (best, balanced or cheapest), the default first.
     *
     * @return array<string, string>
     */
    abstract public static function models(): array;

    /** A readable model name, such as "Claude Opus 5.5". */
    abstract public static function modelName(string $model): string;

    /** Placeholder for the key field. */
    abstract public static function keyPlaceholder(): string;

    /** Where the key is made, shown under the key field. */
    abstract public static function keyPage(): string;

    /**
     * A request that asks for an answer following $schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array{url: string, headers: array<string, string>, body: array<string, mixed>}
     */
    abstract public static function request(string $key, string $model, string $instructions, array $schema, string $question): array;

    /**
     * A tiny request that proves the key and the model work.
     *
     * @return array{url: string, headers: array<string, string>, body: array<string, mixed>}
     */
    abstract public static function testRequest(string $key, string $model): array;

    /**
     * The JSON text of the answer, or null when the service declined, blocked or cut it off.
     *
     * @param  array<string, mixed>  $body
     */
    abstract public static function text(array $body): ?string;

    /**
     * What went wrong with a failed request, as a key of the "errors" translations. Never includes the API key.
     *
     * @param  array<string, mixed>  $body
     */
    public static function failure(int $status, array $body): string
    {
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];
        $code = strtolower((string) ($error['type'] ?? $error['code'] ?? $error['status'] ?? ''));
        $message = strtolower((string) ($error['message'] ?? ''));

        return match (true) {
            $status === 401 || str_contains($code, 'authentication') || str_contains($code, 'invalid_api_key')
                || str_contains($message, 'api key not valid') => 'invalid_key',
            $status === 402 || str_contains($code, 'insufficient_quota') || str_contains($message, 'credit')
                || str_contains($message, 'billing') => 'no_credit',
            $status === 403 || str_contains($code, 'permission') => 'model_not_allowed',
            $status === 404 => 'model_missing',
            $status === 429 || str_contains($code, 'rate_limit') || str_contains($code, 'resource_exhausted') => 'busy',
            $status >= 500 || str_contains($code, 'overloaded') => 'unavailable',
            default => 'failed',
        };
    }
}
