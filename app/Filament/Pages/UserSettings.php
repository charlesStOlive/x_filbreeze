<?php

namespace App\Filament\Pages;

use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rules\Password;
use Illuminate\Contracts\Auth\Authenticatable;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Services\LocaleService;

class UserSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.pages.user-settings';

    protected static ?string $navigationLabel = 'Paramètres utilisateur';

    protected static ?string $title = 'Paramètres utilisateur';

    protected static ?string $navigationGroup = 'Profil';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $profileData = [];
    public ?array $passwordData = [];
    public ?array $localeData = [];

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
        ];
    }

    public function editProfileForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Mon profil')
                    ->description('Gérez vos informations personnelles et vos paramètres de compte.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique('users', 'email', ignoreRecord: true)
                            ->maxLength(255),
                    ])
                    ->footerActions([
                        Forms\Components\Actions\Action::make('saveProfile')
                            ->label('Sauvegarder le profil')
                            ->submit('saveProfile'),
                    ]),
            ])
            ->model($this->getUser())
            ->statePath('profileData');
    }

    public function editLocaleForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Préférences régionales')
                    ->description('Configurez votre fuseau horaire et votre langue préférée.')
                    ->schema([
                        Forms\Components\Select::make('timezone')
                            ->label('Fuseau horaire')
                            ->options(LocaleService::getPopularEuropeanTimezones())
                            ->searchable()
                            ->required()
                            ->default('Europe/Paris')
                            ->helperText('Sélectionnez votre fuseau horaire pour un affichage correct des dates et heures.'),
                        Forms\Components\Select::make('locale')
                            ->label('Langue et région')
                            ->options(LocaleService::getLocales())
                            ->searchable()
                            ->required()
                            ->default('fr_FR')
                            ->helperText('Choisissez votre langue et région pour localiser l\'interface.'),
                    ])
                    ->footerActions([
                        Forms\Components\Actions\Action::make('saveLocaleSettings')
                            ->label('Sauvegarder les préférences')
                            ->submit('saveLocaleSettings'),
                    ]),
            ])
            ->model($this->getUser())
            ->statePath('localeData');
    }

    public function editPasswordForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Modifier le mot de passe')
                    ->description('Assurez-vous que votre compte utilise un mot de passe long et aléatoire pour rester sécurisé.')
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label('Mot de passe actuel')
                            ->password()
                            ->required()
                            ->currentPassword(),
                        Forms\Components\TextInput::make('password')
                            ->label('Nouveau mot de passe')
                            ->password()
                            ->required()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->dehydrateStateUsing(fn($state): string => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('password_confirmation'),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmer le nouveau mot de passe')
                            ->password()
                            ->required()
                            ->dehydrated(false),
                    ])
                    ->footerActions([
                        Forms\Components\Actions\Action::make('savePassword')
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
    }
}
