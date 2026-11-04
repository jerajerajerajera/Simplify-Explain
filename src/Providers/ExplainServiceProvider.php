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
use Simplify\Explain\Livewire\ExplainSettingsForm;
use Simplify\Explain\Livewire\PanelLogButton;
use Simplify\Explain\Livewire\ProblemHost;
use Simplify\Explain\Problems\Problems;
use Simplify\Explain\Services\Explainer;
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
                $icon = svg('tabler-message-question', '', ['style' => 'width: 1rem; height: 1rem;'])->toHtml();
            } catch (Throwable) {
                $icon = '?';
            }

            return '<button type="button" class="simplify-explain-problem" data-simplify-explain="'.e($id).'" title="'.$label.'" aria-label="'.$label.'"'
                .' style="display: inline-grid; place-items: center; width: 1.5rem; height: 1.5rem; margin-inline-start: 0.375rem; vertical-align: middle;'
                .' border: 1px solid rgb(128 128 128 / 0.35); border-radius: 0.375rem; background: transparent; color: inherit; cursor: pointer;">'
                .$icon.'</button>';
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }

    /** The one ProblemHost per page that opens the popup, for people who may use Explain. */
    public static function problemHost(): string
    {
        try {
            return Settings::availableFor(user()) ? Livewire::mount('simplify-explain-problem-host', [], 'simplify-explain-problem-host') : '';
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }
}
