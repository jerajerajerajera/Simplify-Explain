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
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Simplify\Explain\ExplainAddon;
use Simplify\Explain\Filament\Admin\Pages\ExplainSettings;
use Simplify\Explain\Livewire\ExplainPanel;
use Simplify\Explain\Services\PanelLog;
use Simplify\Explain\Services\Settings;

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
    Settings::save(['provider' => 'openai'], removeKey: true);
    must(! Settings::current()['has_key'], 'the key was not removed');
});
step('the add-on describes itself for Simplify', function () use ($admin) {
    if (! class_exists('Simplify\Core\Addons\Addon')) {
        require __DIR__.'/stubs/Addon.php';
    }
    auth()->login($admin);
    $addon = app(ExplainAddon::class);
    must($addon->id() === 'simplify-explain' && $addon->settingsUrl() !== null && $addon->description() !== '', 'the add-on is incomplete');
    $tagged = array_map(fn ($a) => $a::class, iterator_to_array(app()->tagged('simplify.addons')));
    must(in_array(ExplainAddon::class, $tagged, true), 'the add-on is not tagged');
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
