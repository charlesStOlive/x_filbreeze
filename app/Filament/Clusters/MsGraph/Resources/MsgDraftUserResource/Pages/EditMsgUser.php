<?php

namespace App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\Pages;

use Filament\Actions\Action;
use App\Facades\MsGraph\MsgConnect;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\RichEditor;
use Illuminate\Contracts\Support\Htmlable;
use App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource;



class EditMsgUser extends EditRecord
{
    protected static string $resource = MsgDraftUserResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('Voir brouillons');
    }


    protected function getFormActions(): array
    {
        return [];
    }



    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Simuler un email')
                ->icon('heroicon-s-play')
                ->color('primary')
                ->schema([
                    TextInput::make('test_from')->label('From')->default('alexis.clement@suscillon.com'),
                    TextInput::make('test_tos')->label('To')->helperText('Séparer les valeurs par une ",", la première valeur sera la cible MsgraphUser, elle doit exister !')->default(fn() => $this->record->email),
                    TextInput::make('test_bccs')->label('Cc')->helperText('Séparer les valeurs par une ",",'),
                    TextInput::make('subject')->label('Sujet')->default('Hello World !'),
                    RichEditor::make('body')->label('body')->default('<p>##corrige## Du contenu avec des erreurs de frappe !</p>')->helperText('Utilisez ##corrige## pour tester le service de correction, ou ##traduit:en## pour la traduction'),
                ])
                ->modalHeading('Simuler un email pour tester les services')
                ->modalDescription('Cette fonction crée un email de test qui déclenchera les services configurés pour cet utilisateur. Utilisez ##corrige## dans le contenu pour tester la correction, ou ##traduit:en## pour la traduction.')
                ->modalSubmitActionLabel('Lancer la simulation')
                ->action(function (array $data, $livewire) {
                    $fromTemp = $data['test_from'];
                    $toTemp = $data['test_tos'];
                    $dataEmail = $data;
                    $dataEmail['body'] = [
                        'content' => $data['body'],
                        'contentType' => "html",
                    ];
                    $dataEmail['from']['emailAddress']['address'] = $email = trim($fromTemp);
                    $toResipients = [];
                    $tos = explode(',', trim($dataEmail['test_tos']));
                    foreach ($tos as $to) {
                        $toResipients[] = ['emailAddress' => ['address' => trim($to)]];
                    }
                    $dataEmail['toRecipients'] = $toResipients;
                    $bccs = explode(',', trim($dataEmail['test_bccs']));
                    $bccResipients = [];
                    foreach ($bccs as $bcc) {
                        $bccResipients[] = ['emailAddress' => ['address' => trim($bcc)]];
                    }
                    $dataEmail['bccRecipients'] = $bccResipients;
                    $msgUser = $this->record;

                    MsgConnect::launchTestServices($msgUser, $dataEmail);

                    // Rafraîchir le RelationManager des brouillons (pas les emails entrants)
                    $livewire->dispatch('refreshMsgEmailDraftsRelationManager');

                    // Notifier le succès
                    \Filament\Notifications\Notification::make()
                        ->title('Simulation lancée')
                        ->body('L\'email de test a été créé et les services configurés ont été déclenchés.')
                        ->success()
                        ->send();

                    return;
                }),
        ];
    }
}
