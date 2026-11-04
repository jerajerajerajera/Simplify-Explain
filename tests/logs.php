<?php

// Reading warnings from the panel log and the log part of a Wings diagnostics report.
declare(strict_types=1);

use Simplify\Explain\Services\HealthCheck;
use Simplify\Explain\Services\PanelLog;
use Simplify\Explain\Services\WingsLog;

require __DIR__.'/bootstrap.php';

$log = "[2026-10-28 10:00:00] production.INFO: Started\n"
    ."[2026-10-28 10:00:01] production.WARNING: cURL error 28: timed out\n"
    ."#0 /var/www/pelican/vendor/a.php(12)\n"
    ."[2026-10-28 10:00:02] production.DEBUG: noise\n"
    ."[2026-10-28 10:00:03] production.ERROR: Call to a member function on null\n"
    .implode("\n", array_map(fn ($i) => "#$i frame", range(0, 20)));
$lines = PanelLog::entries($log);
check('only warnings and errors', ! str_contains(implode("\n", $lines), 'INFO') && ! str_contains(implode("\n", $lines), 'noise'));
check('message with its first frames', $lines[0] === '[2026-10-28 10:00:01] production.WARNING: cURL error 28: timed out' && $lines[1] === '#0 /var/www/pelican/vendor/a.php(12)');
check('at most 12 lines per entry', count($lines) === 2 + 12);
$many = implode("\n", array_map(fn ($i) => "[2026-10-28 10:00:0$i] production.ERROR: error $i", range(0, 9)));
check('the last eight entries', count(PanelLog::entries($many)) === 8 && PanelLog::entries($many)[0] === '[2026-10-28 10:00:02] production.ERROR: error 2');
check('an empty log', PanelLog::entries('') === []);
PanelLog::$files = __DIR__.'/fixtures/none-*.log';
check('no log files', PanelLog::tail() === '');

$report = "Wings version: 1.12.0\nDocker: 27\n\n------------ Latest Wings Logs ------------\n"
    ."INFO: [Oct 28 10:00:00.000] configured wings with system timezone\n\n"
    ."ERROR: [Oct 28 10:00:05.000] failed to connect to panel\n";
check('only the log part of the report', WingsLog::fromReport($report) === [
    'INFO: [Oct 28 10:00:00.000] configured wings with system timezone',
    'ERROR: [Oct 28 10:00:05.000] failed to connect to panel',
]);
check('a report without heading keeps its last lines', count(WingsLog::fromReport(implode("\n", range(1, 150)))) === 100);

HealthCheck::$stored = fn () => [
    (object) ['label' => 'Node Versions', 'status' => 'ok', 'notificationMessage' => '', 'shortSummary' => 'Ok', 'meta' => []],
    (object) ['label' => 'Queue', 'status' => 'failed', 'notificationMessage' => 'Queue jobs running failed. Check meta for more information.', 'shortSummary' => 'Failed', 'meta' => ['default' => 'The last run of the queue was more than 5 minutes ago.']],
];
check('a health check is named as Simplify names its row', HealthCheck::key('Node Versions') === 'nodeversions' && HealthCheck::key('Queue') === 'queue');
check('only plain check names are accepted', HealthCheck::validKey('queue') && ! HealthCheck::validKey('../queue') && ! HealthCheck::validKey(''));
check('a failed check is found with its result', HealthCheck::find('queue')['status'] === 'failed');
check('a check that is fine gets no explanation', HealthCheck::find('nodeversions') === null && HealthCheck::find('schedule') === null);
check('the result is sent as lines with its details', HealthCheck::lines(HealthCheck::find('queue')) === [
    'Health check: Queue',
    'Status: failed',
    'Message: Queue jobs running failed. Check meta for more information.',
    'Summary: Failed',
    'Details: {"default":"The last run of the queue was more than 5 minutes ago."}',
]);
HealthCheck::$stored = null;

finish('logs');
