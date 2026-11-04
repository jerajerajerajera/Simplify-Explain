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
use Simplify\Explain\Livewire\ProblemButton;
use Simplify\Explain\Livewire\PanelLogButton;
use Simplify\Explain\Services\Explainer;
use Simplify\Explain\Problems\HealthProblems;
use Simplify\Explain\Problems\Problems;
use Simplify\Explain\Services\HealthCheck;
use Simplify\Explain\Services\Settings;
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
        Livewire::component('simplify-explain-problem-button', ProblemButton::class);

        Console::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::console());
        EditNode::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::wingsLog());
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
            fn () => Explainer::mayExplainLogs(user()) ? Blade::render('@livewire(\'simplify-explain-panel-log-button\')') : '',
            scopes: ListLogs::class,
        );

        // Any view can mark an error with this hook (see the README); Explain puts its question button there. The
        // older Health hook is the same with the check's key.
        FilamentView::registerRenderHook(
            'simplify::problem.actions',
            fn (array $scopes = []) => self::problemButton(Problems::fromScopes($scopes)),
            scopes: 'simplify-problem',
        );
        FilamentView::registerRenderHook(
            'simplify::health.check.actions',
            fn (array $scopes = []) => self::problemButton(self::healthId($scopes)),
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

    /** The question button for an error, only when its source lets this user see it and Explain is set up for them. */
    public static function problemButton(?string $id): string
    {
        try {
            if ($id === null || ! Settings::availableFor(user()) || Problems::sourceFor($id, user()) === null) {
                return '';
            }

            return Blade::render(
                '@livewire(\'simplify-explain-problem-button\', [\'problem\' => $id], key(\'simplify-explain-problem-\'.$id))',
                ['id' => $id],
            );
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }

    /** @param  array<mixed>  $scopes */
    private static function healthId(array $scopes): ?string
    {
        foreach ($scopes as $scope) {
            if (is_string($scope) && str_starts_with($scope, 'simplify-health-check:')) {
                $key = substr($scope, strlen('simplify-health-check:'));

                return HealthCheck::validKey($key) ? HealthProblems::id($key) : null;
            }
        }

        return null;
    }
}
