<?php

declare(strict_types=1);

namespace Simplify\Explain\Ai;

/** The AI services a panel can choose from. */
final class Providers
{
    /** Provider id => class, the default first. */
    public const ALL = [
        'anthropic' => Anthropic::class,
        'openai' => OpenAi::class,
        'gemini' => Gemini::class,
        'ollama' => Ollama::class,
    ];

    /** @return class-string<Provider> the provider for an id, or the default for an unknown one */
    public static function get(?string $id): string
    {
        return self::ALL[$id] ?? self::ALL[self::default()];
    }

    public static function default(): string
    {
        return array_key_first(self::ALL);
    }

    public static function has(?string $id): bool
    {
        return isset(self::ALL[$id]);
    }

    /** @return array<string, string> provider id => label */
    public static function labels(): array
    {
        return array_map(fn (string $class) => $class::label(), self::ALL);
    }

    /** The model when it belongs to the provider (any model name on a self-hosted one), otherwise its default model. */
    public static function model(string $provider, ?string $model): string
    {
        $class = self::get($provider);
        $models = $class::models();
        if ($class::selfHosted() && is_string($model) && preg_match('/^[A-Za-z0-9._:\/-]{1,100}$/', $model) === 1) {
            return $model;
        }

        return isset($models[$model]) ? $model : array_key_first($models);
    }

    /** An address of a self-hosted service: http or https with a host, and nothing else. */
    public static function validAddress(string $address): bool
    {
        $parts = parse_url($address);

        return is_array($parts) && in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            && ($parts['host'] ?? '') !== '' && ! isset($parts['user']) && ! isset($parts['pass']);
    }
}
