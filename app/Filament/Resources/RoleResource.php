<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages;
use App\Services\PermissionService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static string | \UnitEnum | null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Rôles';

    protected static ?string $pluralModelLabel = 'Rôles';

    protected static ?string $modelLabel = 'Rôle';

    public static function canViewAny(): bool
    {
        return PermissionService::can('roles.view');
    }

    public static function canCreate(): bool
    {
        return PermissionService::can('roles.create');
    }

    public static function canEdit($record): bool
    {
        return PermissionService::can('roles.edit');
    }

    public static function canDelete($record): bool
    {
        return PermissionService::can('roles.delete');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('guard_name')
                    ->label('Guard')
                    ->default('web')
                    ->required()
                    ->maxLength(255),

                CheckboxList::make('permissions')
                    ->label('Permissions')
                    ->relationship('permissions', 'name')
                    ->options(Permission::all()->pluck('name', 'id'))
                    ->columns([
                        'sm' => 2,
                        'md' => 4,
                    ])
                    ->columnSpanFull()
                    ->searchable(),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->searchable(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Utilisateurs')
                    ->counts('users')
                    ->sortable(),
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
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
