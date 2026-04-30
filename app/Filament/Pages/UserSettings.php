<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Exception;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rules\Password;
use Illuminate\Contracts\Auth\Authenticatable;
use Filament\Forms\Concerns\InteractsWithForms;
use CharlesStOlive\FilamentPermissionManager\Services\ApiTokenService;
use CharlesStOlive\FilamentPermissionManager\Services\LocaleService;

class UserSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected string $view = 'filament.pages.user-settings';

    protected static ?string $navigationLabel = 'Paramètres utilisateur';

    protected static ?string $title = 'Paramètres utilisateur';

    protected static string|\UnitEnum|null $navigationGroup = 'Profil';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $profileData = [];
    public ?array $passwordData = [];
    public ?array $localeData = [];
    public ?array $apiData = [];

    public function mount(): void
    {
        $this->fillForms();
    }

    protected function getForms(): array
    {
        return [
            'editProfileForm',
            'editPasswordForm',
            'editLocaleForm',
            'editApiForm',
        ];
    }

    public function editApiForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clés API personnelles')
                    ->description('Générez des clés API pour accéder à l\'API. La MFA est configurable depuis votre profil.')
                    ->schema([
                        TextInput::make('api_token_name')
                            ->label('Nom de la clé API')
                            ->default('default')
                            ->maxLength(80),
                    ])
                    ->footerActions([
                        Action::make('createApiToken')
                            ->label('Générer une clé API')
                            ->color('warning')
                            ->action('createApiToken'),
                    ]),
            ])
            ->model($this->getUser())
            ->statePath('apiData');
    }

    public function editProfileForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mon profil')
                    ->description('Gérez vos informations personnelles et vos paramètres de compte.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique('users', 'email', ignoreRecord: true)
                            ->maxLength(255),
                    ])
                    ->footerActions([
                        Action::make('saveProfile')
                            ->label('Sauvegarder le profil')
                            ->submit('saveProfile'),
                    ]),
            ])
            ->model($this->getUser())
            ->statePath('profileData');
    }

    public function editLocaleForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Préférences régionales')
                    ->description('Configurez votre fuseau horaire et votre langue préférée.')
                    ->schema([
                        Select::make('timezone')
                            ->label('Fuseau horaire')
                            ->options(LocaleService::getPopularTimezones())
                            ->searchable()
                            ->required()
                            ->default('Europe/Paris')
                            ->helperText('Sélectionnez votre fuseau horaire pour un affichage correct des dates et heures.'),
                        Select::make('locale')
                            ->label('Langue et région')
                            ->options(LocaleService::getLocales())
                            ->searchable()
                            ->required()
                            ->default('fr_FR')
                            ->helperText('Choisissez votre langue et région pour localiser l\'interface.'),
                    ])
                    ->footerActions([
                        Action::make('saveLocaleSettings')
                            ->label('Sauvegarder les préférences')
                            ->submit('saveLocaleSettings'),
                    ]),
            ])
            ->model($this->getUser())
            ->statePath('localeData');
    }

    public function editPasswordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Modifier le mot de passe')
                    ->description('Assurez-vous que votre compte utilise un mot de passe long et aléatoire pour rester sécurisé.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Mot de passe actuel')
                            ->password()
                            ->required()
                            ->currentPassword(),
                        TextInput::make('password')
                            ->label('Nouveau mot de passe')
                            ->password()
                            ->required()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->dehydrateStateUsing(fn($state): string => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('password_confirmation'),
                        TextInput::make('password_confirmation')
                            ->label('Confirmer le nouveau mot de passe')
                            ->password()
                            ->required()
                            ->dehydrated(false),
                    ])
                    ->footerActions([
                        Action::make('savePassword')
                            ->label('Modifier le mot de passe')
                            ->color('warning')
                            ->submit('savePassword'),
                    ]),
            ])
            ->model($this->getUser())
            ->statePath('passwordData');
    }

    private function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);
        return $record;
    }

    private function sendSuccessNotification(string $title = 'Sauvegardé', string $body = 'Les modifications ont été sauvegardées avec succès.'): void
    {
        Notification::make()
            ->success()
            ->title($title)
            ->body($body)
            ->send();
    }

    public function saveProfile(): void
    {
        $data = $this->editProfileForm->getState();
        $this->handleRecordUpdate($this->getUser(), $data);
        $this->sendSuccessNotification('Profil mis à jour', 'Vos informations de profil ont été mises à jour avec succès.');
    }

    public function saveLocaleSettings(): void
    {
        $data = $this->editLocaleForm->getState();
        $this->handleRecordUpdate($this->getUser(), $data);
        $this->sendSuccessNotification('Préférences mises à jour', 'Vos préférences régionales ont été mises à jour avec succès.');
    }

    public function savePassword(): void
    {
        $data = $this->editPasswordForm->getState();

        if (request()->hasSession() && array_key_exists('password', $data)) {
            request()->session()->put(['password_hash_' . Filament::getAuthGuard() => $data['password']]);
        }

        $this->handleRecordUpdate($this->getUser(), $data);
        $this->editPasswordForm->fill(); // Reset les champs sensibles
        $this->sendSuccessNotification('Mot de passe mis à jour', 'Votre mot de passe a été mis à jour avec succès.');
    }

    public function createApiToken(): void
    {
        $data = $this->editApiForm->getState();
        $tokenName = (string) ($data['api_token_name'] ?? 'default');

        $token = app(ApiTokenService::class)->createToken($this->getUser(), $tokenName, ['*']);

        Notification::make()
            ->success()
            ->title('Clé API générée')
            ->body('Copiez-la maintenant (elle ne sera plus visible): ' . $token['plain_text_token'])
            ->send();
    }

    protected function getUser(): Authenticatable & Model
    {
        $user = Filament::auth()->user();
        if (! $user instanceof Model) {
            throw new Exception('The authenticated user object must be an Eloquent model to allow the profile page to update it.');
        }
        return $user;
    }

    protected function fillForms(): void
    {
        $user = $this->getUser();
        $data = $user->attributesToArray();

        $this->editProfileForm->fill($data);
        $this->editLocaleForm->fill($data);
        $this->editPasswordForm->fill();
        $this->editApiForm->fill(['api_token_name' => 'default']);
    }
}