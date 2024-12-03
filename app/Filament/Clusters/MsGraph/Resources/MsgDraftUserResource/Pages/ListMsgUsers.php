<?php

namespace App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\Pages;


use App\Models\MsgUserDraft;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource;

class ListMsgUsers extends ListRecords
{
    protected static string $resource = MsgDraftUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createUser')->label('Ajouter utilisateur')
                ->form([
                    Select::make('msg_id')
                        ->label('Choisissez un Email')
                        ->options(function () {
                            // Charger les options uniquement lorsque le champ est interactif
                            return MsgUserDraft::getApiMsgUsersIdsEmails();
                        })
                        ->searchable()
                        ->lazy(),
                ])
                ->action(function (array $data): void {
                    $msgId = $data['msg_id'];
                    $user = MsgUserDraft::getApiMsgUser($msgId);
                    // \Log::info($user);
                    $secret = Str::uuid();
                    // \Log::info('secret '.$secret);
                    MsgUserDraft::create([
                        'ms_id' => $user['id'],
                        'email' => $user['mail'],
                        'abn_secret' => $secret,
                    ]);

                    return;
                })
        ];
    }
}
