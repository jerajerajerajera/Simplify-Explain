<?php

declare(strict_types=1);

namespace Simplify\Explain;

use Simplify\Core\Addons\Addon;
use Simplify\Core\Filament\Admin\Pages\Settings as SimplifySettings;
use Simplify\Explain\Filament\Admin\Pages\ExplainSettings;
use Simplify\Explain\Livewire\ExplainSettingsForm;
use Simplify\Explain\Services\Settings;

/**
 * How Explain shows up inside Simplify. Only loaded when Simplify asks for its add-ons, so Explain also works
 * without Simplify.
 */
final class ExplainAddon extends Addon
{
    public function id(): string
    {
        return SimplifyExplainPlugin::ID;
    }

    public function name(): string
    {
        return 'Explain';
    }

    public function description(): string
    {
        return __('simplify-explain::settings.description');
    }

    public function icon(): string
    {
        return 'tabler-message-question';
    }

    /** Explain's section in Simplify settings › Add-ons when Simplify has one, otherwise Explain's own page. */
    public function settingsUrl(): ?string
    {
        if (! ExplainSettings::canAccess()) {
            return null;
        }
        if (ExplainSettings::inSimplifySettings() && method_exists(SimplifySettings::class, 'addonUrl')) {
            return SimplifySettings::addonUrl($this->id());
        }

        return ExplainSettings::getUrl(panel: 'admin');
    }

    /** The settings in Simplify settings › Add-ons (add-on API 8); the form checks its user itself. */
    public function settingsForm(): ?string
    {
        return ExplainSettingsForm::class;
    }

    /** Simplify's setup asks for the AI service, its key and who may use Explain, for root admins. */
    public function setupFields(): array
    {
        return ExplainSettings::canAccess() ? [...ExplainSettings::serviceFields(), ExplainSettings::audienceField()] : [];
    }

    /** The saved key is never sent back: an empty key keeps it. */
    public function setupState(): array
    {
        $settings = Settings::current();

        return ['provider' => $settings['provider'], 'model' => $settings['model'], 'key' => '', 'audience' => $settings['audience']];
    }

    public function saveSetup(array $state): void
    {
        if (ExplainSettings::canAccess()) {
            Settings::save($state);
        }
    }
}
