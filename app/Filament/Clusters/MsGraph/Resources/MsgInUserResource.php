<?php

namespace App\Filament\Clusters\MsGraph\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Clusters\MsGraph\Resources\MsgInUserResource\Pages\ListMsgUsers;
use App\Filament\Clusters\MsGraph\Resources\MsgInUserResource\Pages\EditMsgUser;
use App\Models\MsgUserIn;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Filament\Clusters\MsGraph;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Forms\Components\TextInput;
use App\Services\MsGraph\DynamicFormBuilder;
use App\Filament\Components\Tables\MailServiceColumn;
use App\Filament\Clusters\MsGraph\Resources\MsgInUserResource\Pages;
use App\Filament\Clusters\MsGraph\Resources\MsgInUserResource\RelationManagers\MsgEmailInsRelationManager;

class MsgInUserResource extends Resource
{
    protected static ?string $model = MsgUserIn::class;

    protected static string | \BackedEnum | null $navigationIcon = 'fas-right-to-bracket';

    protected static ?string $cluster = MsGraph::class;

    public static function getLabel(): string
    {
        return 'utilisateurs email entrants';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ms_id')->disabled(),
                TextInput::make('email')->disabled(),
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ms_id')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subscription_id'),
                MailServiceColumn::make('services_options')->label('Services')->serviceType('email-in'),
                //
            ])
            ->filters([
                Filter::make('is_test')
                    ->toggle()
                    ->query(fn($query) => $query->where('is_test', true)),
            ])
            ->recordActions([
                Action::make('editServices')
                    ->label('Services')
                    ->icon('heroicon-s-cog-6-tooth')
                    ->schema(fn($record) => DynamicFormBuilder::build($record, 'email-in','services_options',  ))
                    ->action(function (array $data, $record) {
                        foreach ($data as $field => $value) {
                            $record->{$field} = $value;
                        }
                        $record->save();
                    }),
                Action::make('subscribe')
                    ->label('Souscrire')
                    ->requiresConfirmation()
                    ->icon('heroicon-s-envelope-open')
                    ->modalDescription('Activez le mode test au préalable, si vous ne voulez pas modifier le mail')
                    ->action(fn(MsgUserIn $record) => $record->subscribe())
                    ->visible(fn(MsgUserIn $record): bool => $record->subscription_id === null),
                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon('heroicon-s-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(MsgUserIn $record) => $record->revokeSubscription())
                    ->visible(fn(MsgUserIn $record): bool => $record->subscription_id !== null),
                Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-s-arrow-path')
                    ->color('gray')
                    ->action(fn(MsgUserIn $record) => $record->refreshSubscription())
                    ->visible(fn(MsgUserIn $record): bool => $record->subscription_id !== null),
            ])
            ->recordUrl(
                fn(MsgUserIn $record): string => MsgInUserResource::getUrl('edit', ['record' => $record])
            )
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MsgEmailInsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMsgUsers::route('/'),
            'edit' => EditMsgUser::route('/{record}/edit'),
        ];
    }
}
