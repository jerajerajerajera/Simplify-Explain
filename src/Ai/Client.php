<?php

declare(strict_types=1);

namespace Simplify\Explain\Ai;

use Illuminate\Support\Facades\Http;
use Throwable;

/** Sends a provider request and turns a failure into a plain message that never contains the key. */
final class Client
{
    /**
     * @param  class-string<Provider>  $provider
     * @param  array{url: string, headers: array<string, string>, body: array<string, mixed>}  $request
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, error: string}
     */
    public static function send(string $provider, array $request, int $timeout = 90): array
    {
        try {
            $response = Http::withHeaders($request['headers'])
                ->acceptJson()
                ->asJson()
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout($timeout)
                ->post($request['url'], $request['body']);
        } catch (Throwable) {
            return ['ok' => false, 'error' => __('simplify-explain::errors.'.($provider::selfHosted() ? 'server_unreachable' : 'unreachable'), [
                'host' => (string) parse_url($request['url'], PHP_URL_HOST),
                'company' => $provider::company(),
            ])];
        }
        $body = (array) ($response->json() ?? []);
        if (! $response->successful()) {
            return ['ok' => false, 'error' => self::message($provider, $provider::failure($response->status(), $body), $response->status())];
        }

        return ['ok' => true, 'body' => $body];
    }

    /**
     * Proves that a key works with a model.
     *
     * @param  class-string<Provider>  $provider
     * @return array{ok: bool, message: string}
     */
    public static function test(string $provider, string $key, string $model): array
    {
        $result = self::send($provider, $provider::testRequest($key, $model), 30);

        return $result['ok']
            ? ['ok' => true, 'message' => __('simplify-explain::settings.'.($provider::selfHosted() ? 'test_ok_server' : 'test_ok'), ['model' => $provider::modelName($model)])]
            : ['ok' => false, 'message' => $result['error']];
    }

    /** @param  class-string<Provider>  $provider */
    public static function message(string $provider, string $failure, int $status = 0): string
    {
        return __('simplify-explain::errors.'.$failure, ['company' => $provider::company(), 'status' => $status]);
    }
}
