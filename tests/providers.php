<?php

// The requests to the AI services, how their answers are read and how their errors are named.
declare(strict_types=1);

use Simplify\Explain\Ai\Anthropic;
use Simplify\Explain\Ai\Client;
use Simplify\Explain\Ai\Gemini;
use Simplify\Explain\Ai\OpenAi;
use Simplify\Explain\Ai\Providers;

require __DIR__.'/bootstrap.php';

$schema = ['type' => 'object', 'additionalProperties' => false, 'properties' => ['a' => ['type' => 'object', 'additionalProperties' => false]]];

$claude = Anthropic::request('sk-ant-test', 'claude-opus-5-5', 'help', $schema, 'log');
check('claude: own host', $claude['url'] === 'https://api.anthropic.com/v1/messages');
check('claude: key in header only', $claude['headers']['x-api-key'] === 'sk-ant-test' && ! str_contains(json_encode($claude['body']), 'sk-ant-test'));
check('claude: structured output', $claude['body']['output_config']['format']['type'] === 'json_schema');
check('claude: opus falls back', $claude['body']['fallbacks'] === 'default' && $claude['headers']['anthropic-beta'] === 'server-side-fallback-2026-07-01');
$haiku = Anthropic::request('k', 'claude-haiku-5-5', 'help', $schema, 'log');
check('claude: haiku has no fallback', ! isset($haiku['body']['fallbacks']) && ! isset($haiku['headers']['anthropic-beta']));
check('claude: text', Anthropic::text(['content' => [['type' => 'thinking'], ['type' => 'text', 'text' => '{"x":1}']]]) === '{"x":1}');
check('claude: refusal', Anthropic::text(['stop_reason' => 'refusal', 'content' => [['type' => 'text', 'text' => 'x']]]) === null);

$gpt = OpenAi::request('sk-test', 'gpt-6.1-sol', 'help', $schema, 'log');
check('gpt: not stored', $gpt['body']['store'] === false && $gpt['body']['text']['format']['strict'] === true);
check('gpt: text', OpenAi::text(['output' => [['type' => 'reasoning'], ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]]]) === 'ok');
check('gpt: refusal', OpenAi::text(['output' => [['type' => 'message', 'content' => [['type' => 'refusal']]]]]) === null);

$gemini = Gemini::request('AIza', 'gemini-3.8-flash', 'help', $schema, 'log');
check('gemini: model in url', str_ends_with($gemini['url'], '/gemini-3.8-flash:generateContent'));
check('gemini: no additionalProperties', ! str_contains(json_encode($gemini['body']), 'additionalProperties'));
check('gemini: text skips thoughts', Gemini::text(['candidates' => [['content' => ['parts' => [['thought' => true, 'text' => 'x'], ['text' => 'y']]]]]]) === 'y');
check('gemini: blocked', Gemini::text(['candidates' => [['finishReason' => 'SAFETY']]]) === null);

check('failure: 401', Anthropic::failure(401, []) === 'invalid_key');
check('failure: gemini bad key', Gemini::failure(400, ['error' => ['message' => 'API key not valid. Please pass a valid API key.']]) === 'invalid_key');
check('failure: no credit', OpenAi::failure(429, ['error' => ['code' => 'insufficient_quota']]) === 'no_credit');
check('failure: busy', Anthropic::failure(429, ['error' => ['type' => 'rate_limit_error']]) === 'busy');
check('failure: overloaded', Anthropic::failure(529, ['error' => ['type' => 'overloaded_error']]) === 'unavailable');
check('failure: other', Anthropic::failure(400, []) === 'failed');
check('failure message names the company', str_contains(Client::message(OpenAi::class, 'busy'), 'OpenAI is busy'));

check('providers: default', Providers::default() === 'anthropic' && Providers::get('nope') === Anthropic::class);
check('providers: model of another service falls back', Providers::model('gemini', 'claude-opus-5-5') === 'gemini-3.8-flash');
check('providers: own model stays', Providers::model('openai', 'gpt-6-luna') === 'gpt-6-luna');
foreach (Providers::ALL as $class) {
    foreach ($class::models() as $model => $tier) {
        check("$model has a known tier", in_array($tier, ['best', 'balanced', 'cheapest'], true));
        check("$model has a name", $class::modelName($model) !== $model);
    }
}

finish('providers');
