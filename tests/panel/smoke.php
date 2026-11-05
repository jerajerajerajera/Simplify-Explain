<?php

// Loads Simplify Explain inside a real Pelican panel and opens its pages, with a fake Wings and a fake Claude.
// Run from the panel folder after `php artisan p:plugin:install simplify-explain`:
// php plugins/simplify-explain/tests/panel/smoke.php
use App\Filament\Admin\Pages\ListLogs;
use App\Filament\Admin\Resources\Nodes\NodeResource;
use App\Filament\Server\Pages\Console;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Support\PanelSettings;
use Simplify\Explain\Ai\Anthropic;
use Simplify\Explain\Ai\Client;
use Simplify\Explain\Ai\OpenAi;
use Simplify\Explain\ExplainAddon;
use Simplify\Explain\Filament\Admin\Pages\ExplainSettings;
use Simplify\Explain\Livewire\ExplainConnection;
use Simplify\Explain\Livewire\ExplainPanel;
use Simplify\Explain\Livewire\ExplainSettingsForm;
use Simplify\Explain\Livewire\ProblemHost;
use Simplify\Explain\Notifications\ExplainNotification;
use Simplify\Explain\Problems\Problems;
use Simplify\Explain\Providers\ExplainServiceProvider;
use Simplify\Explain\Services\EmergencyAccess;
use Simplify\Explain\Services\HealthCheck;
use Simplify\Explain\Services\LogCheck;
use Simplify\Explain\Services\PanelLog;
use Simplify\Explain\Services\Settings;
use Simplify\Hosting\Providers\HostingServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';

// Each step gets a fresh application, like a real request: Filament keeps panel state for the whole process, so
// opening an admin page and then a server page in one application fails where a browser would not.
function boot(): Application
{
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    // Laravel's console handler would print an uncaught error and still exit 0, which CI reads as a pass.
    set_exception_handler(function (Throwable $e): void {
        echo 'FAIL setup: '.get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
        exit(1);
    });
    Settings::flush();
    fakes();

    return $app;
}

/** A fake Wings and a fake Claude: nothing leaves the test. */
function fakes(bool $found = true): void
{
    Http::fake([
        '*/api/servers/*/logs*' => Http::response(['data' => [
            '[12:00:00 INFO]: Starting minecraft server version 1.21.1',
            '[12:00:05 ERROR]: Could not load mod create: requires flywheel',
            '[12:00:06 INFO]: Player joined from 203.0.113.7',
        ]]),
        '*/api/diagnostics*' => Http::response("Wings 1.12\n--- Latest Wings Logs ---\nERROR: smoke wings failure\n"),
        'api.anthropic.com/*' => Http::response(['stop_reason' => 'end_turn', 'content' => [[
            'type' => 'text',
            'text' => json_encode(['found' => $found, 'summary' => 'The Create mod needs Flywheel.', 'issues' => [
                ['title' => 'Missing Flywheel', 'cause' => 'Create needs Flywheel.', 'steps' => ['Add Flywheel to the mods folder']],
            ]]),
        ]]]),
        // Anything else Wings is asked while a page renders.
        '*:8080/*' => Http::response([]),
    ]);
}

$failed = false;
function step(string $what, callable $run): void
{
    global $failed;
    try {
        $run(boot());
        echo "PASS $what\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "FAIL $what: ".get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    }
}
function visit(User $user, string $url): TestResponse
{
    $client = new class(app())
    {
        use InteractsWithAuthentication, InteractsWithSession, MakesHttpRequests;

        public function __construct(public $app) {}
    };

    return $client->actingAs($user)->get($url);
}
function expectStatus(TestResponse $response, string $url, int $status = 200): void
{
    if ($response->getStatusCode() !== $status) {
        throw new RuntimeException('HTTP '.$response->getStatusCode().' at '.$url.($response->exception ? ': '.$response->exception->getMessage() : ''));
    }
}
function must(bool $ok, string $why): void
{
    if (! $ok) {
        throw new RuntimeException($why);
    }
}

boot();
// Pelican has no root_admin column: root admin is a role.
$admin = User::where('email', 'smoke-explain@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-explain@example.com']);
$admin->syncRoles(Role::getRootAdmin());
$player = User::where('email', 'smoke-explain-player@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-explain-player@example.com']);

step('settings page renders for a root admin', function () use ($admin) {
    $url = ExplainSettings::getUrl(panel: 'admin');
    expectStatus(visit($admin, $url), $url);
});
step('the settings page fits the content card and does not scroll the page', function () use ($admin) {
    $html = visit($admin, ExplainSettings::getUrl(panel: 'admin'))->getContent();
    must((bool) preg_match('/<div class="s-fit">/', $html), 'the settings form is not marked s-fit, so the page scrolls instead of the card');
});
step('the settings form asks before unsaved changes are lost and never saves by itself', function () use ($admin) {
    $html = visit($admin, ExplainSettings::getUrl(panel: 'admin'))->getContent();
    must((bool) preg_match('/data-simplify-unsaved="data" data-simplify-save="save"/', $html), 'the settings form does not opt in to Simplify\'s unsaved-changes bar');
    must(! str_contains($html, 'wire:submit="save"'), 'the settings form has its own <form>, which cannot sit inside Simplify\'s settings');
});
step('the settings form saves on its own and is for root admins only (also in Simplify settings)', function () use ($admin, $player) {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $before = Settings::current();
    Livewire::actingAs($admin)->test(ExplainSettingsForm::class)
        ->assertSee(__('simplify-explain::settings.test'))
        ->set('data.audience', $before['audience'] === 'admins' ? 'everyone' : 'admins')
        ->dispatch('simplify-explain-settings-save');
    must(Settings::current()['audience'] !== $before['audience'], 'the form did not save on the save event');
    Settings::save(['provider' => $before['provider'], 'model' => $before['model'], 'audience' => $before['audience']]);
    // Livewire's tester turns the abort in mount() into a 403 response instead of throwing it.
    try {
        Livewire::actingAs($player)->test(ExplainSettingsForm::class)->assertForbidden();
        $closed = true;
    } catch (HttpException $e) {
        $closed = $e->getStatusCode() === 403;
    }
    must($closed, 'the settings form opens for someone who is not a root admin');
});
step('settings page is closed to other users', function () use ($player) {
    $url = ExplainSettings::getUrl(panel: 'admin');
    must(visit($player, $url)->getStatusCode() !== 200, 'a user without admin rights can open the settings');
});
step('the key is stored encrypted and kept when left empty', function () {
    Settings::save(['provider' => 'openai', 'model' => 'gpt-6-luna', 'audience' => 'admins', 'key' => 'sk-smoke-secret']);
    $stored = (string) DB::table(Settings::TABLE)->value('api_keys');
    must($stored !== '' && ! str_contains($stored, 'sk-smoke-secret'), 'the key is not encrypted');
    Settings::save(['provider' => 'openai', 'model' => 'gpt-6-luna', 'audience' => 'admins', 'key' => '']);
    must(Settings::key('openai') === 'sk-smoke-secret' && Settings::current()['audience'] === 'admins', 'saving without a key lost the key or the choices');
    Settings::save(['provider' => 'anthropic', 'key' => 'sk-ant-other']);
    Settings::forget('openai');
    must(Settings::key('openai') === null && Settings::key('anthropic') === 'sk-ant-other', 'the key was not removed, or another service lost its key');
    Settings::forget('anthropic');
    Settings::save(['provider' => 'openai', 'model' => 'gpt-6-luna', 'audience' => 'admins']);
});
step('the add-on describes itself for Simplify', function () use ($admin) {
    if (! class_exists('Simplify\Core\Addons\Addon')) {
        require __DIR__.'/stubs/Addon.php';
    }
    auth()->login($admin);
    $addon = app(ExplainAddon::class);
    must($addon->id() === 'simplify-explain' && $addon->settingsUrl() !== null && $addon->description() !== '', 'the add-on is incomplete');
    must($addon->settingsForm() === ExplainSettingsForm::class, 'the add-on gives no settings form for Simplify settings');
    $tagged = array_map(fn ($a) => $a::class, iterator_to_array(app()->tagged('simplify.addons')));
    must(in_array(ExplainAddon::class, $tagged, true), 'the add-on is not tagged');
});

step('root admins find Explain\'s AI service under Connections in the account menu, others do not', function () use ($admin, $player) {
    $addon = app(ExplainAddon::class);
    must($addon->connections($admin) === [ExplainConnection::class], 'a root admin gets no Explain row on the Connections page');
    must($addon->connections($player) === [], 'someone who is not a root admin gets Explain\'s row on the Connections page');
    $rows = 'Simplify\Core\Account\Connections';
    if (class_exists($rows)) {
        // With the real Simplify: the core lists the row, so the page and its menu item exist for the root admin.
        must(in_array(ExplainConnection::class, $rows::rows($admin), true), 'Simplify does not list Explain\'s row on the Connections page');
        must(! in_array(ExplainConnection::class, $rows::rows($player), true), 'Simplify lists Explain\'s row for someone who is not a root admin');
        $url = 'Simplify\Core\Filament\App\Pages\Connections'::getUrl(panel: 'app');
        $html = visit($admin, $url)->getContent();
        must(str_contains($html, e(__('simplify-explain::settings.connection_heading'))), 'the Connections page does not show Explain\'s AI service');
    }
    // The row is the service part of the settings: key, Test and Remove key; who may use Explain stays in the settings.
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $before = Settings::current();
    Livewire::actingAs($admin)->test(ExplainConnection::class)
        ->assertSee(__('simplify-explain::settings.test'))
        ->assertDontSee(__('simplify-explain::settings.audience'))
        ->set('data.key', 'row-placeholder-key')
        ->dispatch('simplify-explain-settings-save');
    must(Settings::key($before['provider']) === 'row-placeholder-key', 'the Connections row did not save the key');
    must(Settings::current()['audience'] === $before['audience'], 'saving the Connections row changed who may use Explain');
    Settings::forget($before['provider']);
    try {
        Livewire::actingAs($player)->test(ExplainConnection::class)->assertForbidden();
        $closed = true;
    } catch (HttpException $e) {
        $closed = $e->getStatusCode() === 403;
    }
    must($closed, 'the Connections row opens for someone who is not a root admin');
});

step('Simplify\'s setup asks for the service, key and audience and saves them', function () use ($admin, $player) {
    $addon = app(ExplainAddon::class);
    auth()->login($player);
    must($addon->setupFields() === [], 'a user without admin rights gets the setup fields');
    auth()->login($admin);
    $before = $addon->setupState();
    must(count($addon->setupFields()) === 5 && $before['key'] === '', 'the setup fields are missing or the key is sent back');
    $addon->saveSetup([...$before, 'audience' => 'everyone', 'key' => 'sk-setup-secret']);
    must(Settings::key($before['provider']) === 'sk-setup-secret' && Settings::current()['audience'] === 'everyone', 'the setup answers were not saved');
    $addon->saveSetup([...$before, 'audience' => 'everyone']);
    must(Settings::key($before['provider']) === 'sk-setup-secret', 'an empty key in the setup removed the saved key');
    Settings::forget($before['provider']);
});
$server = null;
step('a test server is made', function () use ($admin, &$server) {
    $node = Node::factory()->create();
    $egg = Egg::query()->first() ?? Egg::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'port' => 25571]);
    $server = Server::factory()->create([
        'owner_id' => $admin->id,
        'node_id' => $node->id,
        'egg_id' => $egg->id,
        'allocation_id' => $allocation->id,
    ]);
    $allocation->update(['server_id' => $server->id]);
    Settings::save(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'audience' => 'everyone', 'key' => 'sk-ant-smoke']);
});
if ($server === null) {
    exit(1);
}
step('the console shows the Explain button once a key is saved', function () use ($admin, $server) {
    $url = Console::getUrl(panel: 'server', tenant: $server);
    $response = visit($admin, $url);
    expectStatus($response, $url);
    must(str_contains($response->getContent(), __('simplify-explain::explain.button')), 'no Explain button on the console');
});
step('Explain reads the console, hides the address and shows the answer', function () use ($admin, $server) {
    Filament::setCurrentPanel(Filament::getPanel('server'));
    auth()->login($admin);
    Filament::setTenant($server);
    $html = Livewire::actingAs($admin)
        ->test(ExplainPanel::class, ['source' => 'console', 'serverId' => $server->id])
        ->call('check')
        ->assertSet('result.ok', true)
        ->assertSet('result.found', true)
        ->html();
    must(str_contains($html, 'Missing Flywheel') && str_contains($html, 'Add Flywheel'), 'the answer is not shown');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.anthropic.com')
        && $request->header('x-api-key')[0] === 'sk-ant-smoke'
        && str_contains($request->body(), 'requires flywheel')
        && ! str_contains($request->body(), '203.0.113.7'));
});
step('someone without access to the server gets no explanation', function () use ($player, $server) {
    Livewire::actingAs($player)
        ->test(ExplainPanel::class, ['source' => 'console', 'serverId' => $server->id])
        ->call('check')
        ->assertSet('result.ok', false);
});
step('root admins can explain the panel log from the log list', function () use ($admin) {
    $url = ListLogs::getUrl(panel: 'admin');
    $response = visit($admin, $url);
    expectStatus($response, $url);
    must(str_contains($response->getContent(), __('simplify-explain::explain.panel_button')), 'no Explain button on the log list');
    $disk = Storage::disk('local');
    $disk->put('simplify-explain-smoke/laravel.log', "[2026-10-28 10:00:00] production.ERROR: smoke panel error from someone@example.com\n#0 frame");
    PanelLog::$files = $disk->path('simplify-explain-smoke').'/*.log';
    Livewire::actingAs($admin)->test(ExplainPanel::class, ['source' => 'panel'])->call('check')->assertSet('result.ok', true);
    Http::assertSent(fn (Request $request) => str_contains($request->body(), 'smoke panel error') && ! str_contains($request->body(), 'someone@example.com'));
    $disk->deleteDirectory('simplify-explain-smoke');
});
step('root admins can explain the Wings log of a node', function () use ($admin, $server) {
    $url = NodeResource::getUrl('edit', ['record' => $server->node], panel: 'admin');
    $response = visit($admin, $url);
    expectStatus($response, $url);
    must(str_contains($response->getContent(), __('simplify-explain::explain.wings_button')), 'no Explain button on the node');
    Livewire::actingAs($admin)->test(ExplainPanel::class, ['source' => 'wings', 'nodeId' => $server->node_id])->call('check')->assertSet('result.ok', true);
    Http::assertSent(fn (Request $request) => str_contains($request->body(), 'smoke wings failure'));
});
step('other users cannot explain the panel or Wings log', function () use ($player, $server) {
    Livewire::actingAs($player)->test(ExplainPanel::class, ['source' => 'panel'])->call('check')->assertSet('result.ok', false);
    Livewire::actingAs($player)->test(ExplainPanel::class, ['source' => 'wings', 'nodeId' => $server->node_id])->call('check')->assertSet('result.ok', false);
});
step('a failed health check gets a question button that explains it, for root admins only', function () use ($admin, $player) {
    RateLimiter::clear('simplify-explain.user.'.$admin->id);
    HealthCheck::$stored = fn () => [
        (object) ['label' => 'Queue', 'status' => 'failed', 'notificationMessage' => 'Queue jobs running failed. Check meta for more information.', 'shortSummary' => 'Failed', 'meta' => ['default' => 'smoke queue heartbeat missing']],
        (object) ['label' => 'Cache', 'status' => 'ok', 'notificationMessage' => '', 'shortSummary' => 'Ok', 'meta' => []],
    ];
    $hook = fn (string $check) => (string) FilamentView::renderHook('simplify::problem.actions', scopes: ['simplify-problem', 'simplify-problem:simplify:health:'.$check]);
    try {
        auth()->login($admin);
        must(str_contains($hook('queue'), 'simplify-explain-problem'), 'no question button on the failed Queue check');
        must(! str_contains($hook('cache'), 'simplify-explain-problem'), 'a question button on a check that is fine');
        must(! str_contains($hook('../queue'), 'simplify-explain-problem'), 'a question button for a made-up check');
        auth()->login($player);
        must(! str_contains($hook('queue'), 'simplify-explain-problem'), 'a question button for someone who is not a root admin');
        auth()->logout();
        Livewire::actingAs($admin)->test(ExplainPanel::class, ['source' => 'problem', 'problem' => 'simplify:health:queue'])->call('check')->assertSet('result.ok', true);
        Http::assertSent(fn (Request $request) => str_contains($request->body(), 'smoke queue heartbeat missing') && str_contains($request->body(), 'Health check: Queue'));
        Livewire::actingAs($admin)->test(ExplainPanel::class, ['source' => 'problem', 'problem' => 'simplify:health:cache'])->call('check')->assertSet('result.ok', false);
        Livewire::actingAs($player)->test(ExplainPanel::class, ['source' => 'problem', 'problem' => 'simplify:health:queue'])->call('check')->assertSet('result.ok', false);
    } finally {
        HealthCheck::$stored = null;
        auth()->logout();
    }
});
step('without Simplify too: red messages, Pelican\'s Health page and its 500 page get Explain', function () use ($admin, $player) {
    RateLimiter::clear('simplify-explain.user.'.$admin->id);
    $core = class_exists(Addon::class);
    try {
        auth()->login($admin);
        $red = Notification::make()->title('Could not start the server')->body('The daemon returned an <b>error</b>: smoke invalid state.')->danger();
        must($red instanceof ExplainNotification, 'Pelican\'s messages are not Explain\'s notification class');
        $red->send();
        $saved = Notification::make()->title('Saved')->success();
        $saved->send();
        $explains = fn (Notification $message) => count(array_filter($message->getActions(), fn ($action) => $action->getName() === Problems::NOTIFICATION_ACTION));
        preg_match('/pelican:message:[A-Za-z0-9]{32}/', json_encode($red->toArray()), $found);
        must(isset($found[0]) && $explains($red) === 1, 'a red message has no Explain link');
        must($explains($saved) === 0, 'a message that is not red has an Explain link');
        $red->send();
        must($explains($red) === 1, 'a red message got the Explain link twice');
        $details = Problems::details($found[0], $admin);
        must(($details['title'] ?? '') === 'Could not start the server' && in_array('Details: The daemon returned an error: smoke invalid state.', $details['lines'], true), 'the red message is not what Explain reads');
        must(Problems::details($found[0], $player) === null, 'someone else can have another person\'s message explained');
        must(Problems::details('pelican:message:'.str_repeat('a', 32), $admin) === null, 'a made-up message can be explained');

        HealthCheck::$stored = fn () => [
            (object) ['label' => 'Queue', 'status' => 'failed', 'notificationMessage' => 'Queue jobs running failed.', 'shortSummary' => 'Failed', 'meta' => ['default' => 'smoke queue all']],
            (object) ['label' => 'Schedule', 'status' => 'failed', 'notificationMessage' => 'The schedule did not run yet.', 'shortSummary' => 'Failed', 'meta' => []],
        ];
        $button = ExplainServiceProvider::healthButton();
        must($core ? $button === '' : str_contains($button, 'data-simplify-explain="simplify:health:all"'), $core ? 'Pelican\'s Health page got a second button next to Simplify\'s' : 'no Explain button on Pelican\'s Health page');
        $all = Problems::details('simplify:health:all', $admin);
        must(in_array('Health check: Queue', $all['lines'] ?? [], true) && in_array('Health check: Schedule', $all['lines'] ?? [], true), 'Explain does not read every failing check');
        must(Problems::details('simplify:health:all', $player) === null, 'someone who is not a root admin can have the health checks explained');
        HealthCheck::$stored = fn () => [];
        must(ExplainServiceProvider::healthButton() === '', 'an Explain button on the Health page while every check is fine');

        must(ExplainServiceProvider::errorLink() === '', 'the emergency link shows outside the error page');
        app()->instance('simplify-explain.error-page', true);
        $link = ExplainServiceProvider::errorLink();
        // Also with Simplify: it has no 500 page of its own, so Pelican's page is the one that needs the link.
        must(str_contains($link, route('simplify-explain.emergency')), 'no emergency link on Pelican\'s 500 page for a root admin');
        auth()->login($player);
        must(ExplainServiceProvider::errorLink() === '', 'the emergency link shows for someone who is not a root admin');
        app()->forgetInstance('simplify-explain.error-page');
        // The real 500 page, rendered as Pelican does. On rc.2 its layout is Filament's simple layout, which runs the
        // hook, so the link has to be in the page; beta38's error layout has no hooks at all, so nothing can add it there.
        $layout = resource_path('views/errors/layout.blade.php');
        if (is_file($layout) && str_contains((string) file_get_contents($layout), 'layout.simple')) {
            // Laravel registers the errors:: views only while it handles an exception; the smoke has to do it itself.
            (new RegisterErrorViewPaths)();
            $render = function (User $user): string {
                auth()->login($user);
                try {
                    return view('errors::500', ['exception' => new RuntimeException('smoke 500')])->render();
                } finally {
                    app('view')->flushState();
                    app()->forgetInstance('simplify-explain.error-page');
                }
            };
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            must(str_contains($render($admin), route('simplify-explain.emergency')), 'Pelican\'s real 500 page has no emergency link for a root admin');
            must(! str_contains($render($player), route('simplify-explain.emergency')), 'Pelican\'s real 500 page shows the emergency link to someone who is not a root admin');
        }
    } finally {
        HealthCheck::$stored = null;
        auth()->logout();
    }
});
step('any plugin can mark an error and give its log; Explain checks who may see it', function () use ($admin, $player) {
    RateLimiter::clear('simplify-explain.user.'.$admin->id);
    $source = new class
    {
        public function canExplainProblem(string $id, $user): bool
        {
            return $id === 'smoke-plugin:job:7' && $user->isRootAdmin();
        }

        public function explainProblem(string $id, $user): ?array
        {
            return $this->canExplainProblem($id, $user) ? ['title' => 'Smoke job 7', 'context' => 'A smoke test job failed.', 'lines' => ['smoke job failed: disk quota exceeded for admin@example.com']] : null;
        }
    };
    app()->instance('smoke.problem.source', $source);
    app()->tag(['smoke.problem.source'], 'simplify.problems');
    $hook = fn (string $id) => (string) FilamentView::renderHook('simplify::problem.actions', scopes: ['simplify-problem', 'simplify-problem:'.$id]);
    try {
        auth()->login($admin);
        must(str_contains($hook('smoke-plugin:job:7'), 'simplify-explain-problem'), 'no question button after a plugin\'s error');
        must(! str_contains($hook('smoke-plugin:job:8'), 'simplify-explain-problem'), 'a question button for an error no plugin claims');
        must(! str_contains($hook('bad id with spaces'), 'simplify-explain-problem'), 'a question button for a malformed id');
        must(str_contains($hook('smoke-plugin:job:__ID__'), 'data-simplify-explain="smoke-plugin:job:__ID__"'), 'no template button for errors drawn in the browser');
        must(str_contains($hook('__ID__'), 'data-simplify-explain="__ID__"'), 'no whole-id template button for the bell');
        must(Problems::notificationAction('smoke-plugin:job:7') !== null && Problems::notificationAction('smoke-plugin:job:8') === null, 'no Explain action for a notification about a claimed error');
        Livewire::actingAs($admin)->test(ProblemHost::class)->dispatch('simplify-explain-problem', problem: 'smoke-plugin:job:7');
        Livewire::actingAs($admin)->test(ProblemHost::class)->call('open', 'smoke-plugin:job:7')->call('open', 'smoke-plugin:job:__ID__')->call('open', 'bad id');
        auth()->login($player);
        must(! str_contains($hook('smoke-plugin:job:7'), 'simplify-explain-problem'), 'a question button for someone the plugin does not allow');
        auth()->logout();
        $result = Livewire::actingAs($admin)->test(ExplainPanel::class, ['source' => 'problem', 'problem' => 'smoke-plugin:job:7'])->call('check')->get('result');
        must($result['ok'] === true && $result['title'] === 'Smoke job 7', 'no explanation of the plugin\'s error: '.json_encode($result));
        Http::assertSent(fn (Request $request) => str_contains($request->body(), 'disk quota exceeded') && ! str_contains($request->body(), 'admin@example.com'));
        Livewire::actingAs($player)->test(ExplainPanel::class, ['source' => 'problem', 'problem' => 'smoke-plugin:job:7'])->call('check')->assertSet('result.ok', false);
    } finally {
        auth()->logout();
    }
});
$own = null;
step('a customer with access gets an explanation, not with audience "admins"', function () use ($player, $server, &$own) {
    $allocation = Allocation::factory()->create(['node_id' => $server->node_id, 'port' => 25572]);
    $own = Server::factory()->create([
        'owner_id' => $player->id,
        'node_id' => $server->node_id,
        'egg_id' => $server->egg_id,
        'allocation_id' => $allocation->id,
    ]);
    $allocation->update(['server_id' => $own->id]);
    Settings::save(['audience' => 'admins']);
    Livewire::actingAs($player)->test(ExplainPanel::class, ['source' => 'console', 'serverId' => $own->id])->call('check')->assertSet('result.ok', false);
    Settings::save(['audience' => 'everyone']);
    Livewire::actingAs($player)->test(ExplainPanel::class, ['source' => 'console', 'serverId' => $own->id])->call('check')->assertSet('result.ok', true);
});
step('customers get "Open a ticket" only with Simplify Hosting and a support link', function () use ($player, &$own) {
    if (! class_exists('Simplify\Core\Support\PanelSettings')) {
        require __DIR__.'/stubs/PanelSettings.php';
    }
    // The stand-in keeps its values in a property; the real core saves them.
    $support = function (?string $url): void {
        if (property_exists(PanelSettings::class, 'values')) {
            PanelSettings::$values = $url ? ['support_url' => $url] : [];
        } else {
            PanelSettings::save(['support_url' => $url]);
        }
    };
    $support('https://support.example.com/ticket');
    $html = fn () => Livewire::actingAs($player)->test(ExplainPanel::class, ['source' => 'console', 'serverId' => $own->id])->call('check')->assertSet('result.found', true)->html();
    must(! str_contains($html(), 'https://support.example.com/ticket'), 'the ticket link shows without Simplify Hosting');
    if (! class_exists('Simplify\Hosting\Providers\HostingServiceProvider')) {
        require __DIR__.'/stubs/HostingServiceProvider.php';
    }
    app()->register(HostingServiceProvider::class);
    must(str_contains($html(), 'https://support.example.com/ticket'), 'no ticket link for a customer with Simplify Hosting');
    $support(null);
    must(! str_contains($html(), __('simplify-explain::explain.ticket')), 'the ticket link shows without a support link');
});
step('asking too often is limited per person and per panel', function () use ($admin, $server) {
    $person = 'simplify-explain.user.'.$admin->id;
    RateLimiter::clear($person);
    RateLimiter::clear('simplify-explain.panel');
    Cache::flush();
    for ($i = 0; $i < LogCheck::PER_USER; $i++) {
        RateLimiter::hit($person, 600);
    }
    $result = Livewire::actingAs($admin)->test(ExplainPanel::class, ['source' => 'console', 'serverId' => $server->id])->call('check')->get('result');
    must($result['ok'] === false && str_contains($result['error'], trans_choice('simplify-explain::explain.too_many', 10, ['minutes' => 10])), 'no limit per person: '.json_encode($result));
    RateLimiter::clear($person);
    for ($i = 0; $i < LogCheck::PER_DAY; $i++) {
        RateLimiter::hit('simplify-explain.panel', 86400);
    }
    $result = Livewire::actingAs($admin)->test(ExplainPanel::class, ['source' => 'console', 'serverId' => $server->id])->call('check')->get('result');
    must($result['ok'] === false && $result['error'] === __('simplify-explain::explain.daily_limit', ['limit' => LogCheck::PER_DAY]), 'no daily limit: '.json_encode($result));
    RateLimiter::clear('simplify-explain.panel');
});
step('Test checks the typed key with the chosen service and never shows the key', function () use ($admin) {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    auth()->login($admin);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['code' => 'invalid_api_key', 'message' => 'Incorrect API key provided: sk-typed-secret']], 401)]);
    Livewire::actingAs($admin)
        ->test(ExplainSettingsForm::class)
        ->set('data.provider', 'openai')
        ->set('data.model', 'gpt-6-luna')
        ->set('data.key', 'sk-typed-secret')
        ->call('test');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.openai.com')
        && $request->header('Authorization')[0] === 'Bearer sk-typed-secret'
        && str_contains($request->body(), 'Reply with OK.'));
    $failed = Client::test(OpenAi::class, 'sk-typed-secret', 'gpt-6-luna');
    must(! $failed['ok'] && $failed['message'] === __('simplify-explain::errors.invalid_key', ['company' => 'OpenAI', 'status' => 401]), 'a wrong key is not reported in plain words');
    $passed = Client::test(Anthropic::class, 'sk-ant-smoke', 'claude-haiku-5-5');
    must($passed['ok'] && ! str_contains($passed['message'].$failed['message'], 'secret'), 'a working key is not reported, or the key is shown');
});
step('Remove the saved key is a button that asks first and keeps unsaved changes', function () use ($admin) {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    auth()->login($admin);
    Settings::save(['provider' => 'openai', 'model' => 'gpt-6-luna', 'audience' => 'admins', 'key' => 'sk-remove-secret']);
    $page = Livewire::actingAs($admin)->test(ExplainSettingsForm::class);
    $html = $page->html();
    must(str_contains($html, __('simplify-explain::settings.remove_key')) && ! str_contains($html, 'data.remove_key') && ! str_contains($html, 'role="switch"'), 'Remove the saved key is still a switch');
    must(ExplainSettingsForm::removeKeyAction()->isConfirmationRequired(), 'Remove the saved key does not ask first');
    $page->set('data.audience', 'everyone')->call('removeKey', 'openai');
    must(Settings::key('openai') === null, 'the key was not removed');
    must(Settings::current()['audience'] === 'admins' && $page->get('data.audience') === 'everyone', 'removing the key saved or lost the other changes');
});
step('Ollama on the admin\'s own server explains the console without a key', function () use ($admin, $server) {
    Http::fake(['192.168.1.10:11434/*' => Http::response(['message' => ['role' => 'assistant', 'content' => json_encode([
        'found' => true, 'summary' => 'Flywheel is missing.', 'issues' => [['title' => 'Ollama saw Flywheel', 'cause' => 'Create needs it.', 'steps' => []]],
    ])]])]);
    Settings::save(['provider' => 'ollama', 'model' => 'qwen3:8b', 'key' => 'http://192.168.1.10:11434']);
    must(Settings::current()['model'] === 'qwen3:8b' && Settings::current()['has_key'], 'the Ollama address or model was not saved');
    Cache::flush();
    Livewire::actingAs($admin)->test(ExplainPanel::class, ['source' => 'console', 'serverId' => $server->id])->call('check')->assertSet('result.summary', 'Flywheel is missing.');
    Http::assertSent(fn (Request $request) => $request->url() === 'http://192.168.1.10:11434/api/chat'
        && ! $request->hasHeader('Authorization') && str_contains($request->body(), 'qwen3:8b'));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    auth()->login($admin);
    $errors = Livewire::actingAs($admin)->test(ExplainSettingsForm::class)->set('data.key', 'file:///etc/passwd')->call('save')->errors();
    must($errors->has('data.key'), 'no error for an address that is not http(s)');
    must(Settings::key('ollama') === 'http://192.168.1.10:11434', 'an address that is not http(s) was saved');
    Settings::save(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5']);
});
step('the emergency page opens for root admins only', function () use ($admin, $player) {
    $url = route('simplify-explain.emergency');
    $response = visit($admin, $url);
    expectStatus($response, $url);
    must(str_contains($response->getContent(), __('simplify-explain::emergency.check_panel')), 'no check button on the emergency page');
    expectStatus(visit($player, $url), $url, 403);
});
step('a temporary address explains the panel log without signing in', function () {
    $disk = Storage::disk('local');
    $disk->put('simplify-explain-smoke/laravel.log', "[2026-10-28 10:00:00] production.ERROR: smoke emergency error\n#0 frame");
    PanelLog::$files = $disk->path('simplify-explain-smoke').'/*.log';
    $client = new class(app())
    {
        use MakesHttpRequests;

        public function __construct(public $app) {}
    };
    $token = EmergencyAccess::issue();
    $url = route('simplify-explain.emergency.access', ['token' => $token]);
    $response = $client->post($url, ['source' => 'panel']);
    expectStatus($response, $url);
    must(str_contains($response->getContent(), 'Missing Flywheel'), 'the answer is not shown');
    Http::assertSent(fn (Request $request) => str_contains($request->body(), 'smoke emergency error'));
    $wrong = route('simplify-explain.emergency.access', ['token' => str_repeat('0', 40)]);
    expectStatus($client->get($wrong), $wrong, 403);
    EmergencyAccess::issue();
    expectStatus($client->get($url), $url, 403);
    $disk->deleteDirectory('simplify-explain-smoke');
});
step('the terminal command explains the panel log and makes an address', function () {
    $disk = Storage::disk('local');
    $disk->put('simplify-explain-smoke/laravel.log', "[2026-10-28 10:00:00] production.ERROR: smoke terminal error\n#0 frame");
    PanelLog::$files = $disk->path('simplify-explain-smoke').'/*.log';
    // Artisan::output() empties its buffer, so it is read once per command.
    $code = Artisan::call('simplify-explain:check', ['--source' => 'panel']);
    $output = Artisan::output();
    must($code === 0 && str_contains($output, 'Missing Flywheel'), 'the command did not print the answer: '.substr($output, 0, 300));
    $code = Artisan::call('simplify-explain:check', ['--url' => true]);
    $output = Artisan::output();
    must($code === 0 && preg_match('#/simplify-explain/emergency/([0-9a-f]{40})#', $output, $m) === 1 && EmergencyAccess::valid($m[1]), 'the printed address does not work');
    $disk->deleteDirectory('simplify-explain-smoke');
});
step('settings and keys from PelicanSimplify are taken over', function () {
    DB::table(Settings::TABLE)->delete();
    $disk = Storage::disk('local');
    $disk->put('simplify/error-explainer.json', json_encode(['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite', 'audience' => 'admins', 'keys' => ['gemini' => encrypt('AIza-old')]]));
    (require dirname(__DIR__, 2).'/database/migrations/2026_10_28_000002_import_pelicansimplify_explain.php')->up();
    Settings::flush();
    $settings = Settings::current();
    must($settings['provider'] === 'gemini' && $settings['audience'] === 'admins' && Settings::key('gemini') === 'AIza-old', 'not taken over: '.json_encode($settings));
    $disk->delete('simplify/error-explainer.json');
});
echo $failed ? "Some steps failed\n" : "All steps passed\n";
exit($failed ? 1 : 0);
