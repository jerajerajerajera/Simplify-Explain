<?php

declare(strict_types=1);

namespace Simplify\Explain;

use Simplify\Core\Addons\Addon;
use Simplify\Explain\Filament\Admin\Pages\ExplainSettings;
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

    public function settingsUrl(): ?string
    {
        return ExplainSettings::canAccess() ? ExplainSettings::getUrl(panel: 'admin') : null;
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
