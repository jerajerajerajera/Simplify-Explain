<?php

declare(strict_types=1);

namespace Simplify\Explain\Providers;

use App\Enums\HeaderActionPosition;
use App\Filament\Admin\Pages\ListLogs;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Server\Pages\Console;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Simplify\Explain\ExplainAddon;
use Simplify\Explain\Filament\ExplainAction;
use Simplify\Explain\Http\Controllers\EmergencyController;
use Simplify\Explain\Livewire\ExplainPanel;
use Simplify\Explain\Livewire\HealthCheckButton;
use Simplify\Explain\Livewire\PanelLogButton;
use Simplify\Explain\Services\Explainer;
use Simplify\Explain\Services\HealthCheck;
use Throwable;

final class ExplainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Simplify reads this tag to list its add-ons. Without Simplify nothing reads it and ExplainAddon is never loaded.
        $this->app->tag([ExplainAddon::class], 'simplify.addons');
    }

    public function boot(): void
    {
        Livewire::component('simplify-explain-panel', ExplainPanel::class);
        Livewire::component('simplify-explain-panel-log-button', PanelLogButton::class);
        Livewire::component('simplify-explain-health-button', HealthCheckButton::class);

        Console::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::console());
        EditNode::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::wingsLog());
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
            fn () => Explainer::mayExplainLogs(user()) ? Blade::render('@livewire(\'simplify-explain-panel-log-button\')') : '',
            scopes: ListLogs::class,
        );

        // Simplify's Health page gives every check that needs attention a spot for add-on buttons. Without Simplify
        // the hook is never rendered.
        FilamentView::registerRenderHook(
            'simplify::health.check.actions',
            fn (array $scopes = []) => self::healthButton($scopes),
            scopes: 'simplify-health-check',
        );

        // The emergency page. The second address comes from `php artisan simplify-explain:check --url` and skips the
        // web middleware, so it also works when sessions are broken.
        Route::match(['get', 'post'], '/simplify-explain/emergency', [EmergencyController::class, 'session'])
            ->middleware('web')
            ->name('simplify-explain.emergency');
        Route::match(['get', 'post'], '/simplify-explain/emergency/{token}', [EmergencyController::class, 'token'])
            ->where('token', '[0-9a-f]{40}')
            ->name('simplify-explain.emergency.access');
    }

    /**
     * The question button for the health check named in the hook's scopes ("simplify-health-check:queue"), only for
     * root admins with a key and only while that check's stored result needs attention.
     *
     * @param  array<mixed>  $scopes
     */
    public static function healthButton(array $scopes): string
    {
        try {
            $key = null;
            foreach ($scopes as $scope) {
                if (is_string($scope) && str_starts_with($scope, 'simplify-health-check:')) {
                    $key = substr($scope, strlen('simplify-health-check:'));
                }
            }
            if ($key === null || ! HealthCheck::validKey($key) || ! Explainer::mayExplainLogs(user()) || ! ($found = HealthCheck::find($key))) {
                return '';
            }
            $label = trans()->has('admin/health.results.'.$key.'.label') ? trans('admin/health.results.'.$key.'.label') : $found['label'];

            return Blade::render(
                '@livewire(\'simplify-explain-health-button\', [\'check\' => $check, \'label\' => $label], key(\'simplify-explain-health-\'.$check))',
                ['check' => $key, 'label' => (string) $label],
            );
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }
}
