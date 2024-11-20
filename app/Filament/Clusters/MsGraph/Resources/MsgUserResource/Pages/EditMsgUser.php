<?php

namespace App\Filament\Clusters\MsGraph\Resources\MsgUserResource\Pages;

use App\Filament\Clusters\MsGraph\Resources\MsgUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMsgUser extends EditRecord
{
    protected static string $resource = MsgUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
