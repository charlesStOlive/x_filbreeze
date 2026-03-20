<?php

namespace App\Filament\Resources;

/**
 * RoleResource - Filament V4 Compatible
 * 
 * IMPORTANT: Ce fichier utilise les signatures Filament V4 :
 * - Schema au lieu de Form
 * - Imports directs des composants
 * - recordActions() et toolbarActions()
 * - BackedEnum/UnitEnum pour navigation
 */

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Spatie\Permission\Models\Role;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\RoleResource\Pages;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;
use CharlesStOlive\FilamentPermissionManager\Traits\HasFilamentAuthorization;

class RoleResource extends Resource
{
    use HasFilamentAuthorization;

    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Rôles';

    protected static ?string $pluralModelLabel = 'Rôles';

    protected static ?string $modelLabel = 'Rôle';



    public static function canEdit($record): bool
    {
        // Empêcher l'édition du rôle Super Admin par des non-Super Admin
        if ($record && $record->name === 'Super Admin') {
            return auth()->user()->hasRole('Super Admin') && PermissionService::can('role.edit');
        }

        return PermissionService::can('role.edit');
    }

    public static function canDelete($record): bool
    {
        // Empêcher la suppression du rôle Super Admin
        if ($record && $record->name === 'Super Admin') {
            return false;
        }

        // Vérifier si le rôle est utilisé par des utilisateurs
        if ($record && $record->users()->count() > 0) {
            return false; // Empêcher la suppression si utilisé
        }

        return PermissionService::can('role.delete');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextEntry::make('super_admin_notice')
                    ->label('')
                    ->state('🔥 Ce rôle a une commande pour garantir tous les droits à chaque MAJ')
                    ->visible(fn($record) => $record && $record->name === 'Super Admin')
                    ->columnSpanFull(),

                CheckboxList::make('permissions')
                    ->label('Permissions')
                    ->relationship('permissions', 'name')
                    ->options(PermissionService::getAllPermissions())
                    ->columns(2)
                    ->columnSpanFull()
                    ->hidden(fn($record) => $record && $record->name === 'Super Admin'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Super Admin' ? 'danger' : 'gray'),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->name === 'Super Admin'
                            ? '∞ (Tous les droits)'
                            : $state;
                    }),
                TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(function ($record) {
                        return static::canEdit($record);
                    }),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Supprimer le rôle')
                    ->modalDescription(function ($record) {
                        if ($record->name === 'Super Admin') {
                            return 'Le rôle Super Admin ne peut pas être supprimé.';
                        }
                        if ($record->users()->count() > 0) {
                            return 'Ce rôle est attribué à ' . $record->users()->count() . ' utilisateur(s). Retirez-le d\'abord de tous les utilisateurs avant de le supprimer.';
                        }
                        return 'Êtes-vous sûr de vouloir supprimer ce rôle ?';
                    })
                    ->modalSubmitActionLabel('Supprimer')
                    ->visible(function ($record) {
                        return static::canDelete($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Supprimer les rôles sélectionnés')
                        ->modalDescription('Seuls les rôles non utilisés seront supprimés (Super Admin exclu).')
                        ->action(function ($records) {
                            $deletableRecords = $records->filter(function ($record) {
                                return $record->name !== 'Super Admin' && $record->users()->count() === 0;
                            });
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
