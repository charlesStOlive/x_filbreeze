<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use CharlesStOlive\FilamentPermissionManager\Services\ApiTokenService;
use CharlesStOlive\FilamentPermissionManager\Services\LocaleService;


class EditProfile extends BaseEditProfile
{
    public ?string $freshToken = null;
    public array $apiTokens = [];

    public function mount(): void
    {
        parent::mount();
        $this->loadTokens();
    }

    public function loadTokens(): void
    {
        $user = $this->getUser();
        if (! method_exists($user, 'tokens')) {
            return;
        }
        $this->apiTokens = $user->tokens()
            ->where('revoked', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($t) => [
                'id'         => (string) $t->id,
                'name'       => $t->name,
                'created_at' => $t->created_at->format('d/m/Y H:i'),
            ])
            ->toArray();
    }

    public function revokeToken(string $tokenId): void
    {
        app(ApiTokenService::class)->revokeUserToken($this->getUser(), $tokenId);
        $this->freshToken = null;
        $this->loadTokens();
        Notification::make()->success()->title('Clé révoquée')->send();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            Section::make('Préférences régionales')
                ->description('Configurez votre fuseau horaire et votre langue préférée.')
                ->columns(2)
                ->schema([
                    Select::make('timezone')
                        ->label('Fuseau horaire')
                        ->options(LocaleService::getPopularTimezones())
                        ->searchable()
                        ->required()
                        ->default('Europe/Paris'),
                    Select::make('locale')
                        ->label('Langue et région')
                        ->options(LocaleService::getLocales())
                        ->searchable()
                        ->required()
                        ->default('fr_FR'),
                ]),
            Section::make('Clés API')
                ->description('Gérez vos personal access tokens Passport.')
                ->afterHeader([
                    Action::make('createApiToken')
                        ->label('Générer une clé API')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->modalHeading('Générer un personal access token')
                        ->modalDescription('Choisissez un nom pour identifier cette clé.')
                        ->schema([
                            TextInput::make('token_name')
                                ->label('Nom de la clé')
                                ->default('default')
                                ->required()
                                ->maxLength(80),
                        ])
                        ->action(function (array $data): void {
                            $token = app(ApiTokenService::class)->createToken(
                                $this->getUser(),
                                $data['token_name'],
                                ['*']
                            );
                            $this->freshToken = $token['plain_text_token'];
                            $this->loadTokens();
                            Notification::make()
                                ->success()
                                ->title('Clé API générée')
                                ->body('Un token est apparu dans la section "Clés API" ci-dessous.')
                                ->send();
                        }),
                ])
                ->schema([
                    TextEntry::make('freshToken')
                        ->label('🔑 Nouveau token — copiez-le maintenant, il ne sera plus visible !')
                        ->state(fn() => $this->freshToken)
                        ->visible(fn() => filled($this->freshToken))
                        ->fontFamily(FontFamily::Mono)
                        ->color('warning')
                        ->copyable()
                        ->copyMessage('Copié !')
                        ->copyMessageDuration(1500),
                    RepeatableEntry::make('apiTokens')
                        ->label('Tokens actifs')
                        ->state(fn() => $this->apiTokens)
                        ->table([
                            TableColumn::make('Clé'),
                            TableColumn::make('Créé le'),
                            TableColumn::make('Actions')->hiddenHeaderLabel(),
                        ])
                        ->schema([
                            TextEntry::make('name')
                                ->hiddenLabel()
                                ->weight(FontWeight::Medium),
                            TextEntry::make('created_at')
                                ->hiddenLabel(),
                            TextEntry::make('id')
                                ->hiddenLabel()
                                ->formatStateUsing(fn() => '')
                                ->suffixAction(
                                    Action::make('revoke')
                                        ->icon('heroicon-o-trash')
                                        ->color('danger')
                                        ->requiresConfirmation()
                                        ->modalHeading('Révoquer ce token ?')
                                        ->action(fn(string $state) => $this->revokeToken($state))
                                ),
                        ]),
                ]),
        ]);
    }
}
