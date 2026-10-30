<?php

declare(strict_types=1);

namespace Simplify\Explain\Ai;

/**
 * A model on the administrator's own Ollama server, through its chat API with a JSON schema for the answer.
 * The "key" of this service is the server's address; there is no API key.
 */
final class Ollama extends Provider
{
    public static function company(): string
    {
        return 'Ollama';
    }

    public static function label(): string
    {
        return 'Ollama on your own server';
    }

    public static function selfHosted(): bool
    {
        return true;
    }

    /** Only the default: any model downloaded on the server can be typed in. */
    public static function models(): array
    {
        return ['llama3.3' => 'balanced'];
    }

    public static function modelName(string $model): string
    {
        return $model;
    }

    public static function keyPlaceholder(): string
    {
        return 'http://192.168.1.10:11434';
    }

    public static function keyPage(): string
    {
        return 'ollama.com';
    }

    public static function request(string $key, string $model, string $instructions, array $schema, string $question): array
    {
        return [
            'url' => self::endpoint($key),
            'headers' => [],
            'body' => [
                'model' => $model,
                'messages' => [['role' => 'system', 'content' => $instructions], ['role' => 'user', 'content' => $question]],
                'format' => $schema,
                'stream' => false,
            ],
        ];
    }

    public static function testRequest(string $key, string $model): array
    {
        return [
            'url' => self::endpoint($key),
            'headers' => [],
            'body' => [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => 'Reply with OK.']],
                'stream' => false,
                'options' => ['num_predict' => 16],
            ],
        ];
    }

    public static function text(array $body): ?string
    {
        $text = (string) ($body['message']['content'] ?? '');

        return $text !== '' ? $text : null;
    }

    public static function failure(int $status, array $body): string
    {
        return $status === 404 ? 'server_model_missing' : parent::failure($status, $body);
    }

    /** The chat endpoint on the saved address; empty (so the request fails as unreachable) for anything but http(s). */
    private static function endpoint(string $address): string
    {
        $address = rtrim(trim($address), '/');

        return Providers::validAddress($address) ? $address.'/api/chat' : '';
    }
}
