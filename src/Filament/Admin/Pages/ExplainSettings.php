<?php

declare(strict_types=1);

namespace Simplify\Explain\Filament\Admin\Pages;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
                    ...self::serviceFields(),
                    Toggle::make('remove_key')
                        ->label(__('simplify-explain::settings.remove_key'))
                        ->visible(fn (Get $get) => ! self::selfHosted($get) && Settings::key((string) $get('provider')) !== null),
                ]),
            Section::make(__('simplify-explain::settings.access'))
                ->schema([
                    self::audienceField(),
                ]),
        ]);
    }

    /**
     * The AI service, its model and the API key; also asked in Simplify's setup.
     *
     * @return list<Select|TextInput>
     */
    public static function serviceFields(): array
    {
        return [
            Select::make('provider')
                ->label(__('simplify-explain::settings.provider'))
                ->options(Providers::labels())
                ->selectablePlaceholder(false)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => $set('model', Providers::model((string) $get('provider'), null))),
            Select::make('model')
                ->label(__('simplify-explain::settings.model'))
                ->options(fn (Get $get) => self::modelOptions((string) $get('provider')))
                ->selectablePlaceholder(false)
                ->required()
                ->visible(fn (Get $get) => ! self::selfHosted($get)),
            // A self-hosted service runs whatever model was downloaded on it, so its name is typed in.
            TextInput::make('model')
                ->label(__('simplify-explain::settings.model'))
                ->placeholder(fn (Get $get) => array_key_first(Providers::get((string) $get('provider'))::models()))
                ->regex('/^[A-Za-z0-9._:\/-]{1,100}$/')
                ->helperText(__('simplify-explain::settings.model_help_server'))
                ->required()
                ->visible(fn (Get $get) => self::selfHosted($get)),
            TextInput::make('key')
                ->label(fn (Get $get) => __('simplify-explain::settings.'.(self::selfHosted($get) ? 'address' : 'key')))
                ->password(fn (Get $get) => ! self::selfHosted($get))
                ->rule(fn (Get $get) => fn (string $attribute, mixed $value, Closure $fail) => self::selfHosted($get)
                    && filled($value) && ! Providers::validAddress((string) $value) ? $fail(__('simplify-explain::settings.address_invalid')) : null)
                ->autocomplete(false)
                ->maxLength(300)
                ->placeholder(fn (Get $get) => self::keyPlaceholder((string) $get('provider')))
                ->helperText(fn (Get $get) => __('simplify-explain::settings.'.(self::selfHosted($get) ? 'address_help' : 'key_help'), [
                    'page' => Providers::get((string) $get('provider'))::keyPage(),
                    'company' => Providers::get((string) $get('provider'))::company(),
                ]))
                ->columnSpanFull(),
        ];
    }

    /** Who sees the Explain button; also asked in Simplify's setup. */
    public static function audienceField(): Select
    {
        return Select::make('audience')
            ->label(__('simplify-explain::settings.audience'))
            ->options([
                'everyone' => __('simplify-explain::settings.audience_everyone'),
                'admins' => __('simplify-explain::settings.audience_admins'),
            ])
            ->selectablePlaceholder(false)
            ->required()
            ->helperText(__('simplify-explain::settings.audience_help'));
    }

    protected function getHeaderActions(): array
    {
        // Test and Save in the head of the page, as on Simplify's own pages (and in host mode's title bar).
        return [
            Action::make('test')
                ->label(__('simplify-explain::settings.test'))
                ->icon('tabler-plug-connected')
                ->color('gray')
                ->action(fn () => $this->test()),
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
            Notification::make()->title(__('simplify-explain::settings.'.(Providers::get($provider)::selfHosted() ? 'test_no_address' : 'test_no_key')))->warning()->send();

            return;
        }
        $result = Client::test(Providers::get($provider), $key, Providers::model($provider, data_get($this->data, 'model')));
        Notification::make()
            ->title(match (true) {
                ! $result['ok'] => __('simplify-explain::settings.test_failed'),
                Providers::get($provider)::selfHosted() => __('simplify-explain::settings.test_passed_server', ['company' => Providers::get($provider)::company()]),
                default => __('simplify-explain::settings.test_passed'),
            })
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    private static function selfHosted(Get $get): bool
    {
        return Providers::get((string) $get('provider'))::selfHosted();
    }

    /** The saved address of a self-hosted service is not secret, so it is shown; a saved key never is. */
    private static function keyPlaceholder(string $provider): string
    {
        $saved = Settings::key($provider);
        if ($saved !== null) {
            return Providers::get($provider)::selfHosted() ? $saved : __('simplify-explain::settings.key_saved');
        }

        return Providers::get($provider)::keyPlaceholder();
    }

    /** @return array<string, string> model id => "name (price)" */
    private static function modelOptions(string $provider): array
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
