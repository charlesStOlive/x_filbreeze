<?php

namespace App\Filament\Clusters\Administration\RoleResource\Schemas;

use CharlesStOlive\FilamentPermissionManager\Support\SuperAdmin;
use CharlesStOlive\FilamentPermissionManager\Forms\Components\PermissionEditor;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextEntry::make('super_admin_notice')
                    ->label('')
                    ->state('🔥 Ce rôle reçoit toutes les permissions à chaque synchronisation (permissions:sync, puis permissions:grant-super-admin).')
                    ->visible(fn($record) => $record && SuperAdmin::isRole($record))
                    ->columnSpanFull(),

                // Rangées comme l'application : une carte par liste (actions, portée, états, transitions), avec « tout
                // cocher » et « tout, y compris ce qui sera ajouté ». Le Super Admin reçoit tout à chaque synchronisation.
                PermissionEditor::make('permissions')
                    ->label('Permissions')
                    ->disabled(fn ($record): bool => SuperAdmin::isRole($record))
                    ->columnSpanFull(),

            ]);
    }
}
