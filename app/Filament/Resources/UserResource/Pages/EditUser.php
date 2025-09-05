<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Si un nouveau mot de passe est fourni, le hacher
        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // Si aucun mot de passe n'est fourni, ne pas modifier le mot de passe existant
            unset($data['password']);
        }

        // Supprimer le champ de confirmation du mot de passe
        unset($data['password_confirmation']);

        return $data;
    }
}
