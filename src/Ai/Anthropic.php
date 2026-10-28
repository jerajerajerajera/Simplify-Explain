<?php

declare(strict_types=1);

namespace Simplify\Explain\Ai;

/** Claude through the Messages API. Plain HTTP, because a Pelican plugin cannot add the SDK as a Composer package. */
final class Anthropic extends Provider
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public static function company(): string
    {
        return 'Anthropic';
    }

    public static function label(): string
    {
        return 'Claude by Anthropic';
    }

    public static function models(): array
    {
        return [
            'claude-opus-5-5' => 'best',
            'claude-sonnet-5-5' => 'balanced',
            'claude-haiku-5-5' => 'cheapest',
        ];
    }

    public static function modelName(string $model): string
    {
        return match ($model) {
            'claude-opus-5-5' => 'Claude Opus 5.5',
            'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
            'claude-haiku-5-5' => 'Claude Haiku 5.5',
            default => $model,
        };
    }

    public static function keyPlaceholder(): string
    {
        return 'sk-ant-…';
    }

    public static function keyPage(): string
    {
        return 'console.anthropic.com';
    }

    public static function request(string $key, string $model, string $instructions, array $schema, string $question): array
    {
        $body = [
            'model' => $model,
            'max_tokens' => 8000,
            'system' => $instructions,
            'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            'messages' => [['role' => 'user', 'content' => $question]],
        ];
        // A log can look like a security topic. Opus and Sonnet then hand the question to a fitting model
        // instead of declining it. Haiku has no such fallback.
        $fallbacks = $model !== 'claude-haiku-5-5';
        if ($fallbacks) {
            $body['fallbacks'] = 'default';
        }

        return ['url' => self::ENDPOINT, 'headers' => self::headers($key, $fallbacks), 'body' => $body];
    }

    public static function testRequest(string $key, string $model): array
    {
        return [
            'url' => self::ENDPOINT,
            'headers' => self::headers($key, false),
            'body' => [
                'model' => $model,
                'max_tokens' => 64,
                'output_config' => ['effort' => 'low'],
                'messages' => [['role' => 'user', 'content' => 'Reply with OK.']],
            ],
        ];
    }

    public static function text(array $body): ?string
    {
        if (($body['stop_reason'] ?? null) === 'refusal') {
            return null;
        }
        foreach ((array) ($body['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                return (string) ($block['text'] ?? '');
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private static function headers(string $key, bool $fallbacks): array
    {
        $headers = ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'];
        if ($fallbacks) {
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
        }

        return $headers;
    }
}
