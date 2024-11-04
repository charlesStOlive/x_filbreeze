<?php

namespace App\Filament\Clusters\Crm\Resources\ContactResource\Pages;

use App\Filament\Clusters\Crm\Resources\ContactResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateContact extends CreateRecord
{
    protected static string $resource = ContactResource::class;
}
