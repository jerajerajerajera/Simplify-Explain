<?php

// Loads Simplify Explain inside a real Pelican panel and opens its pages. Run from the panel folder after
// `php artisan p:plugin:install simplify-explain`: php plugins/simplify-explain/tests/panel/smoke.php
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Simplify\Explain\ExplainAddon;
use Simplify\Explain\Filament\Admin\Pages\ExplainSettings;
use Simplify\Explain\Services\Settings;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Laravel's console handler would print an uncaught error and still exit 0, which CI reads as a pass.
set_exception_handler(function (Throwable $e) {
    echo 'FAIL uncaught '.get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    exit(1);
});

$failed = false;
function step(string $what, callable $run): void
{
    global $failed;
    try {
        $run();
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
    $status = visit($player, $url)->getStatusCode();
    if ($status === 200) {
        throw new RuntimeException('a user without admin rights can open the settings');
    }
});
step('the key is stored encrypted and kept when left empty', function () {
    Settings::save(['provider' => 'openai', 'model' => 'gpt-6-luna', 'audience' => 'admins', 'key' => 'sk-smoke-secret']);
    $stored = (string) DB::table(Settings::TABLE)->value('api_keys');
    if ($stored === '' || str_contains($stored, 'sk-smoke-secret')) {
        throw new RuntimeException('the key is not encrypted');
    }
    Settings::save(['provider' => 'openai', 'model' => 'gpt-6-luna', 'audience' => 'admins', 'key' => '']);
    if (Settings::key('openai') !== 'sk-smoke-secret' || Settings::current()['audience'] !== 'admins') {
        throw new RuntimeException('saving without a key lost the key or the choices');
    }
    Settings::save(['provider' => 'openai'], removeKey: true);
    if (Settings::current()['has_key']) {
        throw new RuntimeException('the key was not removed');
    }
});
step('the add-on describes itself for Simplify', function () use ($admin) {
    if (! class_exists('Simplify\Core\Addons\Addon')) {
        require __DIR__.'/stubs/Addon.php';
    }
    auth()->login($admin);
    $addon = app(ExplainAddon::class);
    if ($addon->id() !== 'simplify-explain' || $addon->settingsUrl() === null || $addon->description() === '') {
        throw new RuntimeException('the add-on is incomplete');
    }
    if (! in_array(ExplainAddon::class, array_map(fn ($a) => $a::class, iterator_to_array(app()->tagged('simplify.addons'))), true)) {
        throw new RuntimeException('the add-on is not tagged');
    }
});
echo $failed ? "Some steps failed\n" : "All steps passed\n";
exit($failed ? 1 : 0);
