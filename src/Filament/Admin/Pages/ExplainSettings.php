<?php

declare(strict_types=1);

namespace Simplify\Explain\Filament\Admin\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Simplify\Core\Addons\Addon;
use Simplify\Explain\Livewire\ExplainSettingsForm;

/**
 * Explain's own settings page, with the same form as Simplify settings › Add-ons. With a Simplify that shows add-on
 * settings there (add-on API 8), the page leaves the menu; it still opens by its address.
 */
final class ExplainSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'tabler-message-question';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'simplify-explain';

    protected string $view = 'simplify-explain::settings';

    public static function canAccess(): bool
    {
        return ExplainSettingsForm::canAccess();
    }

    /** Whether Simplify shows Explain's settings in its own settings (Simplify's ADDONS.md, "Settings"). */
    public static function inSimplifySettings(): bool
    {
        return class_exists(Addon::class) && defined(Addon::class.'::API') && Addon::API >= 8;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! self::inSimplifySettings();
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify-explain::settings.title');
    }

    /** In the Simplify group of Pelican's menu, with the other Simplify add-ons (Simplify's ADDONS.md, "Admin menu"). */
    public static function getNavigationGroup(): ?string
    {
        return 'Simplify';
    }

    public function getTitle(): string
    {
        return __('simplify-explain::settings.title');
    }

    /** @return list<Select|TextInput> also asked in Simplify's setup */
    public static function serviceFields(): array
    {
        return ExplainSettingsForm::serviceFields();
    }

    public static function audienceField(): Select
    {
        return ExplainSettingsForm::audienceField();
    }

    protected function getHeaderActions(): array
    {
        // Save in the head of the page, as on Simplify's own pages; the form saves itself on this event.
        return [
            Action::make('save')
                ->label(__('simplify-explain::settings.save'))
                ->icon('tabler-device-floppy')
                ->dispatch('simplify-explain-settings-save')
                ->keyBindings(['mod+s']),
        ];
    }
}
