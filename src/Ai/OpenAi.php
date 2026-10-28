<?php

declare(strict_types=1);

namespace Simplify\Explain\Ai;

/** GPT through the OpenAI Responses API, with a strict JSON schema and without storing the request. */
final class OpenAi extends Provider
{
    public const ENDPOINT = 'https://api.openai.com/v1/responses';

    public static function company(): string
    {
        return 'OpenAI';
    }

    public static function label(): string
    {
        return 'GPT by OpenAI';
    }

    public static function models(): array
    {
        return [
            'gpt-6.1-sol' => 'balanced',
            'gpt-6-astra' => 'best',
            'gpt-6-luna' => 'cheapest',
        ];
    }

    public static function modelName(string $model): string
    {
        return match ($model) {
            'gpt-6.1-sol' => 'GPT-6.1 Sol',
            'gpt-6-astra' => 'GPT-6 Astra',
            'gpt-6-luna' => 'GPT-6 Luna',
            default => $model,
        };
    }

    public static function keyPlaceholder(): string
    {
        return 'sk-…';
    }

    public static function keyPage(): string
    {
        return 'platform.openai.com';
    }

    public static function request(string $key, string $model, string $instructions, array $schema, string $question): array
    {
        return [
            'url' => self::ENDPOINT,
            'headers' => ['Authorization' => 'Bearer '.$key],
            'body' => [
                'model' => $model,
                'instructions' => $instructions,
                'input' => $question,
                'max_output_tokens' => 8000,
                'reasoning' => ['effort' => 'low'],
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'log_check', 'schema' => $schema, 'strict' => true]],
                'store' => false,
            ],
        ];
    }

    public static function testRequest(string $key, string $model): array
    {
        return [
            'url' => self::ENDPOINT,
            'headers' => ['Authorization' => 'Bearer '.$key],
            'body' => [
                'model' => $model,
                'input' => 'Reply with OK.',
                'max_output_tokens' => 64,
                'reasoning' => ['effort' => 'low'],
                'store' => false,
            ],
        ];
    }

    public static function text(array $body): ?string
    {
        foreach ((array) ($body['output'] ?? []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'refusal') {
                    return null;
                }
                if (($part['type'] ?? null) === 'output_text') {
                    return (string) ($part['text'] ?? '');
                }
            }
        }

        return null;
    }
}
