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

    /** The model when it belongs to the provider, otherwise the provider's default model. */
    public static function model(string $provider, ?string $model): string
    {
        $models = self::get($provider)::models();

        return isset($models[$model]) ? $model : array_key_first($models);
    }
}
