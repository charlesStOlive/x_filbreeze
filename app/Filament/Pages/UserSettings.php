<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Support\Exceptions\Halt;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class UserSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.pages.user-settings';

    protected static ?string $navigationLabel = 'Paramètres utilisateur';

    protected static ?string $title = 'Paramètres utilisateur';

    protected static ?string $navigationGroup = 'Profil';

    public ?array $profileData = [];
    public ?array $passwordData = [];

    public function mount(): void
    {
        $this->fillForms();
    }

    protected function fillForms(): void
    {
        $user = auth()->user();

        $this->profileForm->fill([
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function profileForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique('users', 'email', auth()->user())
                    ->maxLength(255),
            ])
            ->statePath('profileData');
    }

    public function passwordForm(Form $form): Form
    {
        return $form
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
                    ->same('password_confirmation')
                    ->validationAttribute('mot de passe'),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label('Confirmer le nouveau mot de passe')
                    ->password()
                    ->required()
                    ->dehydrated(false),
            ])
            ->statePath('passwordData');
    }

    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }

    public function updateProfile(): void
    {
        try {
            $data = $this->profileForm->getState();

            auth()->user()->update($data);

            Notification::make()
                ->success()
                ->title('Profil mis à jour')
                ->body('Vos informations de profil ont été mises à jour avec succès.')
                ->send();
        } catch (Halt $exception) {
            return;
        }
    }

    public function updatePassword(): void
    {
        try {
            $data = $this->passwordForm->getState();

            auth()->user()->update([
                'password' => Hash::make($data['password']),
            ]);

            $this->passwordForm->fill([]);

            Notification::make()
                ->success()
                ->title('Mot de passe mis à jour')
                ->body('Votre mot de passe a été mis à jour avec succès.')
                ->send();
        } catch (Halt $exception) {
            return;
        }
    }
}
