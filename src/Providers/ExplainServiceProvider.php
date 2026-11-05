<?php

declare(strict_types=1);

namespace Simplify\Explain\Providers;

use App\Enums\HeaderActionPosition;
use App\Filament\Admin\Pages\Health;
use App\Filament\Admin\Pages\ListLogs;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Server\Pages\Console;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Simplify\Core\Addons\Addon;
use Simplify\Explain\ExplainAddon;
use Simplify\Explain\Filament\ExplainAction;
use Simplify\Explain\Http\Controllers\EmergencyController;
use Simplify\Explain\Livewire\ExplainPanel;
use Simplify\Explain\Livewire\ExplainSettingsForm;
use Simplify\Explain\Livewire\PanelLogButton;
use Simplify\Explain\Livewire\ProblemHost;
use Simplify\Explain\Notifications\ExplainNotification;
use Simplify\Explain\Problems\HealthProblems;
use Simplify\Explain\Problems\Problems;
use Simplify\Explain\Services\Explainer;
use Simplify\Explain\Services\HealthCheck;
use Simplify\Explain\Services\Settings;
use Throwable;

final class ExplainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Simplify reads this tag to list its add-ons. Without Simplify nothing reads it and ExplainAddon is never loaded.
        $this->app->tag([ExplainAddon::class], 'simplify.addons');

        // Every red message in Pelican gets Explain's link, with or without Simplify (see ExplainNotification).
        if (! $this->app->bound(Notification::class)) {
            $this->app->bind(Notification::class, ExplainNotification::class);
        }
    }

    public function boot(): void
    {
        Livewire::component('simplify-explain-panel', ExplainPanel::class);
        Livewire::component('simplify-explain-panel-log-button', PanelLogButton::class);
        Livewire::component('simplify-explain-settings-form', ExplainSettingsForm::class);
        Livewire::component('simplify-explain-problem-host', ProblemHost::class);

        Console::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::console());
        EditNode::registerCustomHeaderActions(HeaderActionPosition::Before, ExplainAction::wingsLog());
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
            fn () => Explainer::mayExplainLogs(user()) ? Blade::render('@livewire(\'simplify-explain-panel-log-button\')') : '',
            scopes: ListLogs::class,
        );

        // Any view can mark an error with this hook (see the README); Explain puts its question button there.
        FilamentView::registerRenderHook(
            'simplify::problem.actions',
            fn (array $scopes = []) => self::problemButton(Problems::fromScopes($scopes)),
            scopes: 'simplify-problem',
        );
        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, fn () => self::problemHost());

        // Without Simplify, Pelican's own Health page and 500 page get Explain too. With Simplify, its Health rows and
        // error spots carry the question button instead.
        FilamentView::registerRenderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, fn () => self::healthButton(), scopes: Health::class);
        View::composer('errors::500', fn () => app()->instance('simplify-explain.error-page', true));
        FilamentView::registerRenderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, fn () => self::errorLink());

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
     * The question button for an error: plain HTML, opened by ProblemHost. Shown when Explain is set up for this user
     * and the error's source lets them see it; for a template id (with __ID__) the source checks the real id on click.
     */
    public static function problemButton(?string $id): string
    {
        try {
            if ($id === null || ! Settings::availableFor(user()) || (! Problems::isTemplate($id) && Problems::sourceFor($id, user()) === null)) {
                return '';
            }
            $label = e(__('simplify-explain::explain.button'));
            try {
                $icon = svg('tabler-message-question')->toHtml();
            } catch (Throwable) {
                $icon = '?';
            }

            // Styled by the class (resources/views/styles.blade.php, on the page with ProblemHost).
            return '<button type="button" class="simplify-explain-problem sx-problem-btn" data-simplify-explain="'.e($id).'" title="'.$label.'" aria-label="'.$label.'">'
                .$icon.'</button>';
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }

    /** One Explain button on Pelican's Health page for every check that fails or warns, for root admins without Simplify. */
    public static function healthButton(): string
    {
        try {
            if (class_exists(Addon::class) || ! Settings::availableFor(user()) || ! Explainer::mayExplainLogs(user()) || HealthCheck::failing() === []) {
                return '';
            }

            return view('simplify-explain::health-button', ['problem' => HealthProblems::id(HealthProblems::ALL)])->render();
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }

    /** The link to the emergency page on Pelican's 500 page, for root admins with a saved key, without Simplify. */
    public static function errorLink(): string
    {
        try {
            if (! app()->bound('simplify-explain.error-page') || class_exists(Addon::class) || ! Explainer::mayExplainLogs(user())) {
                return '';
            }

            return view('simplify-explain::error-link', ['url' => route('simplify-explain.emergency')])->render();
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }

    /** The one ProblemHost per page that opens the popup, for people who may use Explain. */
    public static function problemHost(): string
    {
        try {
            return Settings::availableFor(user())
                ? view('simplify-explain::styles')->render().Livewire::mount('simplify-explain-problem-host', [], 'simplify-explain-problem-host')
                : '';
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }
}
