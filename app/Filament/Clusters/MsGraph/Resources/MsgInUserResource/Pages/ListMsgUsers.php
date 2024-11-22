<?php

namespace App\Filament\Clusters\MsGraph\Resources\MsgInUserResource\Pages;


use App\Models\MsgUserIn;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\MsGraph\Resources\MsgInUserResource;

class ListMsgUsers extends ListRecords
{
    protected static string $resource = MsgInUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createUser')
                ->form([
                    Select::make('msg_id')
                        ->label('Choisissez un Email')
                        ->options(function () {
                            // Charger les options uniquement lorsque le champ est interactif
                            return MsgUserIn::getApiMsgUsersIdsEmails();
                        })
                        ->searchable()
                        ->lazy(),
                ])
                ->action(function (array $data): void {
                    $msgId = $data['msg_id'];
                    $user = MsgUserIn::getApiMsgUser($msgId);
                    // \Log::info($user);
                    $secret = Str::uuid();
                    // \Log::info('secret '.$secret);
                    MsgUserIn::create([
                        'ms_id' => $user['id'],
                        'email' => $user['mail'],
                        'abn_secret' => $secret,
                    ]);

                    return;
                })
        ];
    }
}
