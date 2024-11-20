<?php

namespace App\Filament\Clusters\MsGraph\Resources\MsgUserResource\Pages;

use App\Filament\Clusters\MsGraph\Resources\MsgUserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMsgUser extends CreateRecord
{
    protected static string $resource = MsgUserResource::class;
}
