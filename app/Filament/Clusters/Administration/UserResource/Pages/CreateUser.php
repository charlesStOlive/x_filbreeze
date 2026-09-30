<?php

namespace App\Filament\Clusters\Administration\UserResource\Pages;

use App\Filament\Clusters\Administration\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
