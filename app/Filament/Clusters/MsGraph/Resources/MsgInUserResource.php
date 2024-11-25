<?php

namespace App\Filament\Clusters\MsGraph\Resources;

use Filament\Forms\Form;
use App\Models\MsgUserIn;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Filament\Clusters\MsGraph;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Forms\Components\TextInput;
use App\Tables\Columns\MailServiceColumn;
use App\Services\MsGraph\DynamicFormBuilder;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Clusters\MsGraph\Resources\MsgInUserResource\Pages;
use App\Filament\Clusters\MsGraph\Resources\MsgInUserResource\RelationManagers\MsgEmailInsRelationManager;

class MsgInUserResource extends Resource
{
    protected static ?string $model = MsgUserIn::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $cluster = MsGraph::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('ms_id')->disabled(),
                TextInput::make('email')->disabled(),
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('ms_id')->searchable()->sortable(),
                TextColumn::make('suscription_id'),
                MailServiceColumn::make('services_options')->serviceType('email-in'),
                //
            ])
            ->filters([
                Filter::make('is_test')
                    ->toggle()
                    ->query(fn($query) => $query->where('is_test', true)),
            ])
            ->actions([
                Action::make('editServices')
                    ->label('Services')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->form(fn($record) => DynamicFormBuilder::build($record, 'email-in','services_options',  ))
                    ->action(function (array $data, $record) {
                        foreach ($data as $field => $value) {
                            $record->{$field} = $value;
                        }
                        $record->save();
                    }),
                Action::make('suscribe')
                    ->label('Souscrire')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-envelope-open')
                    ->modalDescription('Activez le mode test au préalable, si vous ne voulez pas modifier le mail')
                    ->action(fn(MsgUserIn $record) => $record->suscribe())
                    ->visible(fn(MsgUserIn $record): bool => $record->suscription_id === null),
                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(MsgUserIn $record) => $record->revokeSuscription())
                    ->visible(fn(MsgUserIn $record): bool => $record->suscription_id !== null),
                Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(fn(MsgUserIn $record) => $record->refreshSuscription())
                    ->visible(fn(MsgUserIn $record): bool => $record->suscription_id !== null),
            ])
            ->recordUrl(
                fn(MsgUserIn $record): string => MsgInUserResource::getUrl('edit', ['record' => $record])
            )
            ->bulkActions([
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
            'index' => Pages\ListMsgUsers::route('/'),
            'edit' => Pages\EditMsgUser::route('/{record}/edit'),
        ];
    }
}
