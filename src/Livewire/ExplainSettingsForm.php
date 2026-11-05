<?php

declare(strict_types=1);

namespace Simplify\Explain\Livewire;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Simplify\Explain\Ai\Client;
use Simplify\Explain\Ai\Providers;
use Simplify\Explain\Services\Settings;

/**
 * Explain's settings: the AI service and model, the API key and who may use Explain. Shown on Explain's own settings
 * page and, with Simplify, in Simplify settings › Add-ons (Simplify's ADDONS.md, "Settings"). Root admins only.
 *
 * @property Schema $form
 */
class ExplainSettingsForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->fillForm();
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            self::serviceSection(__('simplify-explain::settings.service')),
            Section::make(__('simplify-explain::settings.access'))
                ->schema([
                    self::audienceField(),
                ]),
        ]);
    }

    /** The AI service with its key, Test and Remove key; also the whole of Explain's row on Simplify's Connections page. */
    protected static function serviceSection(string $heading): Section
    {
        return Section::make($heading)
            ->description(__('simplify-explain::settings.service_help'))
            ->columns(2)
            ->schema([
                ...self::serviceFields(),
                Actions::make([
                    self::testAction(),
                    self::removeKeyAction()
                        ->visible(fn (Get $get) => ! self::selfHosted($get) && Settings::key((string) $get('provider')) !== null),
                ])->columnSpanFull(),
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

    /** Test sits in the form: the add-on section in Simplify's settings has no title bar of its own. */
    public static function testAction(): Action
    {
        return Action::make('test')
            ->label(__('simplify-explain::settings.test'))
            ->icon('tabler-plug-connected')
            ->color('gray')
            ->action(fn (self $livewire) => $livewire->test());
    }

    /** Saves the form; also called by Save in the page's title bar and by Simplify's Save for unsaved changes. */
    #[On('simplify-explain-settings-save')]
    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        Settings::save($state);
        $this->fillForm();
        Notification::make()->title(__('simplify-explain::settings.saved'))->success()->send();
    }

    /** A button with a confirmation, not a switch: the key goes at once, other changes stay unsaved. */
    public static function removeKeyAction(): Action
    {
        return Action::make('removeKey')
            ->label(__('simplify-explain::settings.remove_key'))
            ->icon('tabler-trash')
            ->color('gray')
            ->requiresConfirmation()
            ->action(fn (Get $get, self $livewire) => $livewire->removeKey((string) $get('provider')));
    }

    /** Forgets the saved key of one service, after the confirmation. */
    public function removeKey(string $provider): void
    {
        abort_unless(self::canAccess(), 403);
        Settings::forget($provider);
        Notification::make()->title(__('simplify-explain::settings.key_removed'))->success()->send();
    }

    /** Checks the typed key, or the saved one, with the chosen model. */
    #[On('simplify-explain-settings-test')]
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
        ]);
    }

    public function render(): View
    {
        return view('simplify-explain::livewire.settings-form');
    }
}
