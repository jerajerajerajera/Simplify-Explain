<?php

declare(strict_types=1);

namespace Simplify\Explain;

use Simplify\Core\Addons\Addon;
use Simplify\Explain\Filament\Admin\Pages\ExplainSettings;

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
}
