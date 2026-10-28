<?php

// Cleaning the log lines, the question and reading the answer.
declare(strict_types=1);

use Simplify\Explain\Services\LogCheck;

require __DIR__.'/bootstrap.php';

$clean = LogCheck::sanitize([
    "\e[33m[12:00:01] [Server thread/WARN]: Can't keep up!\e[0m",
    'Player joined from 203.0.113.7:51234',
    'IPv6 2001:db8:85a3::8a2e:370:7334 and fe80:0:0:0:200:f8ff:fe21:67cf',
    'mail me at someone@example.com',
    'password=hunter2 token: abc API_KEY=xyz',
    'session ab12cd34ef56ab12cd34ef56ab12cd34ef56',
    '   ',
    str_repeat('x', 800),
]);
check('colour codes removed', $clean[0] === "[12:00:01] [Server thread/WARN]: Can't keep up!");
check('IPv4 hidden', $clean[1] === 'Player joined from [hidden ip]:51234');
check('IPv6 hidden', ! str_contains($clean[2], 'fe21') && str_contains($clean[2], '[hidden ip]'));
check('e-mail hidden', $clean[3] === 'mail me at [hidden email]');
check('secrets hidden', $clean[4] === 'password=[hidden] token: [hidden] API_KEY=[hidden]');
check('long tokens hidden', $clean[5] === 'session [hidden]');
check('empty lines dropped, long lines cut', count($clean) === 7 && mb_strlen($clean[6]) === 600);
check('only the last lines are kept', count(LogCheck::sanitize(range(1, 250))) === LogCheck::LINES && LogCheck::sanitize(range(1, 250))[0] === '151');

$question = LogCheck::question(['a', 'b'], 'Game or software: Paper');
check('question has context and log', str_starts_with($question, "Game or software: Paper\n") && str_contains($question, "<log>\na\nb\n</log>"));

check('answer language follows the panel', str_ends_with(LogCheck::instructions('nl'), 'Answer in Dutch.'));
check('regional locale', str_ends_with(LogCheck::instructions('pt_BR'), 'Answer in Brazilian Portuguese.'));
check('unknown locale falls back to its language or English', str_ends_with(LogCheck::instructions('de_AT'), 'Answer in German.') && str_ends_with(LogCheck::instructions('xx'), 'Answer in English.'));
foreach (glob(__DIR__.'/../lang/*', GLOB_ONLYDIR) as $dir) {
    check('language name for '.basename($dir), isset(LogCheck::LANGUAGES[basename($dir)]));
}

$answer = LogCheck::answer(json_encode([
    'found' => true,
    'summary' => ' The server crashed. ',
    'issues' => [
        ['title' => 'Missing mod', 'cause' => 'Create needs Flywheel.', 'steps' => ['Add Flywheel', ' ', 'Restart', 'a', 'b', 'c', 'd']],
        ['title' => ' ', 'cause' => 'skipped', 'steps' => []],
        ['title' => 'Two', 'cause' => '', 'steps' => []], ['title' => 'Three', 'cause' => '', 'steps' => []],
        ['title' => 'Four', 'cause' => '', 'steps' => []], ['title' => 'Five', 'cause' => '', 'steps' => []],
    ],
]));
check('answer read', $answer['found'] === true && $answer['summary'] === 'The server crashed.');
check('issues without title skipped, at most four looked at', count($answer['issues']) === 3);
check('empty steps dropped, at most five', $answer['issues'][0]['steps'] === ['Add Flywheel', 'Restart', 'a', 'b', 'c']);
check('found false', LogCheck::answer('{"found":false,"summary":"Fine","issues":[]}')['found'] === false);
check('broken answer', LogCheck::answer('not json') === null && LogCheck::answer(null) === null && LogCheck::answer('{"found":true}') === null);
check('schema requires every property', LogCheck::SCHEMA['required'] === array_keys(LogCheck::SCHEMA['properties']));

finish('logcheck');
