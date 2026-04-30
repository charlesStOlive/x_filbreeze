<?php

namespace App\Filament\Resources;


use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Spatie\Permission\Models\Permission;
use App\Filament\Resources\PermissionResource\Pages;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;
use CharlesStOlive\FilamentPermissionManager\Traits\HasFilamentAuthorization;

class PermissionResource extends Resource
{
    use HasFilamentAuthorization;

    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?string $pluralModelLabel = 'Permissions';

    protected static ?string $modelLabel = 'Permission';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('guard_name')
                    ->label('Guard')
                    ->default('web')
                    ->required(),
                Textarea::make('description')
                    ->label('Description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->searchable(),
                TextColumn::make('roles_count')
                    ->label('Rôles')
                    ->counts('roles'),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}