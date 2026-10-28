<?php

declare(strict_types=1);

namespace Simplify\Explain\Filament\Admin\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Simplify\Explain\Ai\Client;
use Simplify\Explain\Ai\Providers;
use Simplify\Explain\Services\Settings;

/**
 * Where a root admin chooses the AI service and model, who may use Explain, and enters the API key.
 *
 * @property Schema $form
 */
final class ExplainSettings extends Page implements HasSchemas
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-message-question';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'simplify-explain';

    protected string $view = 'simplify-explain::settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
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

    public function mount(): void
    {
        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('simplify-explain::settings.service'))
                ->description(__('simplify-explain::settings.service_help'))
                ->columns(2)
                ->schema([
                    Select::make('provider')
                        ->label(__('simplify-explain::settings.provider'))
                        ->options(Providers::labels())
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => $set('model', Providers::model((string) $get('provider'), null))),
                    Select::make('model')
                        ->label(__('simplify-explain::settings.model'))
                        ->options(fn (Get $get) => $this->modelOptions((string) $get('provider')))
                        ->selectablePlaceholder(false)
                        ->required(),
                    TextInput::make('key')
                        ->label(__('simplify-explain::settings.key'))
                        ->password()
                        ->autocomplete(false)
                        ->maxLength(300)
                        ->placeholder(fn (Get $get) => Settings::key((string) $get('provider'))
                            ? __('simplify-explain::settings.key_saved')
                            : Providers::get((string) $get('provider'))::keyPlaceholder())
                        ->helperText(fn (Get $get) => __('simplify-explain::settings.key_help', [
                            'page' => Providers::get((string) $get('provider'))::keyPage(),
                            'company' => Providers::get((string) $get('provider'))::company(),
                        ]))
                        ->columnSpanFull(),
                    Toggle::make('remove_key')
                        ->label(__('simplify-explain::settings.remove_key'))
                        ->visible(fn (Get $get) => Settings::key((string) $get('provider')) !== null),
                    Actions::make([
                        Action::make('test')
                            ->button()
                            ->label(__('simplify-explain::settings.test'))
                            ->icon('tabler-plug-connected')
                            ->color('gray')
                            ->action(fn () => $this->test()),
                    ])->columnSpanFull(),
                ]),
            Section::make(__('simplify-explain::settings.access'))
                ->schema([
                    Select::make('audience')
                        ->label(__('simplify-explain::settings.audience'))
                        ->options([
                            'everyone' => __('simplify-explain::settings.audience_everyone'),
                            'admins' => __('simplify-explain::settings.audience_admins'),
                        ])
                        ->selectablePlaceholder(false)
                        ->required()
                        ->helperText(__('simplify-explain::settings.audience_help')),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('simplify-explain::settings.save'))
                ->icon('tabler-device-floppy')
                ->action('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        Settings::save($state, ! empty($state['remove_key']));
        $this->fillForm();
        Notification::make()->title(__('simplify-explain::settings.saved'))->success()->send();
    }

    /** Checks the typed key, or the saved one, with the chosen model. */
    public function test(): void
    {
        abort_unless(self::canAccess(), 403);
        $provider = Providers::has(data_get($this->data, 'provider')) ? (string) data_get($this->data, 'provider') : Providers::default();
        $key = trim((string) data_get($this->data, 'key', '')) ?: Settings::key($provider);
        if ($key === null) {
            Notification::make()->title(__('simplify-explain::settings.test_no_key'))->warning()->send();

            return;
        }
        $result = Client::test(Providers::get($provider), $key, Providers::model($provider, data_get($this->data, 'model')));
        Notification::make()
            ->title($result['ok'] ? __('simplify-explain::settings.test_passed') : __('simplify-explain::settings.test_failed'))
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    /** @return array<string, string> model id => "name (price)" */
    private function modelOptions(string $provider): array
    {
        $class = Providers::get($provider);
        $options = [];
        foreach ($class::models() as $model => $tier) {
            $options[$model] = $class::modelName($model).' ('.__('simplify-explain::settings.tier_'.$tier).')';
        }

        return $options;
    }

    private function fillForm(): void
    {
        $settings = Settings::current();
        $this->form->fill([
            'provider' => $settings['provider'],
            'model' => $settings['model'],
            'audience' => $settings['audience'],
            'key' => '',
            'remove_key' => false,
        ]);
    }
}
