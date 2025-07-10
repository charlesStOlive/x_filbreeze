<?php

namespace App\Filament\Clusters\Crm\Resources\CompanyResource\Pages;

use App\Filament\Clusters\Crm\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Services\MsGraph\MsGraphEmailService;
use App\Dto\MsGraph\EmailMessageDTO;
use Illuminate\Support\Facades\Auth;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;



    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('createEmailDraft')
                ->label('Créer un Draft Email')
                ->form(fn(\Filament\Forms\Form $form, $record) => [
                    Forms\Components\Select::make('to')
                        ->label('Destinataires')
                        ->multiple()
                        ->preload()
                        ->options(
                            $record->contacts
                                ->filter(fn($contact) => filled($contact->email))
                                ->mapWithKeys(fn($contact) => [$contact->email => $contact->email])
                                ->toArray()
                        )
                        ->required(),

                    Forms\Components\TextInput::make('subject')
                        ->label('Sujet')
                        ->required(),

                    Forms\Components\RichEditor::make('body')
                        ->label('Contenu')
                        ->required(),
                ])
                ->action(function (array $data, $record) {
                    /** @var \App\Models\User $user */
                    $user = Auth::user();

                    // Destinataires
                    $to = EmailMessageDTO::formatRecipientsFromEmails($data['to']);

                    // Construction du DTO
                    $dto = EmailMessageDTO::fromUserInput([
                        'subject' => $data['subject'],
                        'body' => $data['body'],
                        'to' => $to,
                    ]);

                    app(MsGraphEmailService::class)->createNewDraftFromScratch($user, $dto);

                    Notification::make()
                        ->title('Brouillon créé')
                        ->success()
                        ->send();
                })
                ->modalHeading('Nouveau Brouillon Email')
                ->modalSubmitActionLabel('Créer')
                ->icon('heroicon-o-envelope')
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
