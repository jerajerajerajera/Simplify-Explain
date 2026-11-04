<?php

declare(strict_types=1);

/*
 * Checks for the update path in CI, against whichever Explain is installed in the panel at that moment:
 *
 *   php plugins/simplify-explain/tests/panel/update-check.php fill    fills in every setting, with a made-up key
 *   php plugins/simplify-explain/tests/panel/update-check.php kept    the settings are still there and the page opens
 *   php plugins/simplify-explain/tests/panel/update-check.php opens   the settings page opens (settings may be gone)
 *
 * It only uses classes that 1.1.0 already had, so it also runs against the old and the previous version.
 * The key is a placeholder, never a real one.
 */

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Simplify\Explain\Services\Settings;

const PLACEHOLDER_KEY = 'upgrade-test-placeholder-not-a-key';

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? '';

function fail(string $why): never
{
    echo "FAIL $why\n";
    exit(1);
}

function settingsPageStatus(): int
{
    $admin = User::where('email', 'update-explain@example.com')->first()
        ?? User::factory()->create(['email' => 'update-explain@example.com']);
    $admin->syncRoles(Role::getRootAdmin());
    $client = new class(app())
    {
        use InteractsWithAuthentication, InteractsWithSession, MakesHttpRequests;

        public function __construct(public $app) {}
    };
    $response = $client->actingAs($admin)->get('/admin/simplify-explain');
    if ($response->exception) {
        echo 'The settings page threw: '.substr($response->exception->getMessage(), 0, 400)."\n";
    }

    return $response->getStatusCode();
}

if ($mode === 'fill') {
    Settings::save(['provider' => 'openai', 'model' => 'gpt-4.1-mini', 'audience' => 'admins', 'key' => PLACEHOLDER_KEY]);
    Settings::flush();
    $settings = Settings::current();
    if ($settings['provider'] !== 'openai' || $settings['audience'] !== 'admins' || ! $settings['has_key']) {
        fail('the old version did not save the settings: '.json_encode($settings));
    }
    echo "PASS settings filled in\n";
    exit(0);
}

if ($mode === 'kept') {
    Settings::flush();
    $settings = Settings::current();
    echo 'Settings: '.json_encode($settings)."\n";
    if ($settings['provider'] !== 'openai' || $settings['audience'] !== 'admins') {
        fail('the service or the audience was not kept');
    }
    if (Settings::key('openai') !== PLACEHOLDER_KEY) {
        fail('the saved key was not kept or no longer decrypts');
    }
    $status = settingsPageStatus();
    if ($status !== 200) {
        fail('the settings page answers HTTP '.$status);
    }
    echo "PASS the settings were kept and the settings page opens\n";
    exit(0);
}

if ($mode === 'opens') {
    $status = settingsPageStatus();
    if ($status !== 200) {
        fail('the settings page answers HTTP '.$status);
    }
    Settings::flush();
    echo 'Settings now: '.json_encode(Settings::current())."\n";
    echo "PASS the settings page opens\n";
    exit(0);
}

fail('use fill, kept or opens');
