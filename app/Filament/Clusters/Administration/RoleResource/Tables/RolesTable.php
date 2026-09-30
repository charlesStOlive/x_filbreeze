<?php

namespace App\Filament\Clusters\Administration\RoleResource\Tables;

use CharlesStOlive\FilamentPermissionManager\Support\SuperAdmin;
use App\Filament\Clusters\Administration\RoleResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->badge()
                    ->color(fn(string $state): string => SuperAdmin::isRole($state) ? 'danger' : 'gray'),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->formatStateUsing(fn($state, $record) => SuperAdmin::isRole($record)
                        ? '∞ (Tous les droits)'
                        : $state),
                TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->visible(fn($record) => RoleResource::canEdit($record)),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer le rôle')
                    ->modalDescription(function ($record) {
                        if (SuperAdmin::isRole($record)) {
                            return 'Le rôle Super Admin ne peut pas être supprimé.';
                        }
                        if ($record->users()->count() > 0) {
                            return 'Ce rôle est attribué à ' . $record->users()->count() . ' utilisateur(s). Retirez-le d\'abord de tous les utilisateurs avant de le supprimer.';
                        }

                        return 'Êtes-vous sûr de vouloir supprimer ce rôle ?';
                    })
                    ->modalSubmitActionLabel('Supprimer')
                    ->visible(fn($record) => RoleResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Supprimer les rôles sélectionnés')
                        ->modalDescription('Seuls les rôles non utilisés seront supprimés (Super Admin exclu).')
                        ->action(function ($records) {
                            $deletableRecords = $records->filter(
                                fn($record) => ! SuperAdmin::isRole($record) && $record->users()->count() === 0
                            );
                            $deletableRecords->each->delete();

                            $skipped = $records->count() - $deletableRecords->count();
                            if ($skipped > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Suppression partielle')
                                    ->body("{$skipped} rôle(s) ignoré(s) car encore utilisé(s) ou protégé(s)")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
