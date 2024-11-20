<?php

namespace App\Filament\Clusters\MsGraph\Resources;

use Filament\Tables;
use App\Models\MsgUser;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use App\Filament\Clusters\MsGraph;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\ToggleColumn;
use App\Services\MsGraph\DynamicFormBuilder;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Clusters\MsGraph\Resources\MsgUserResource\Pages;
use App\Filament\Clusters\MsGraph\Resources\MsgUserResource\RelationManagers\MsgEmailInsRelationManager;

class MsgUserResource extends Resource
{
    protected static ?string $model = MsgUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $cluster = MsGraph::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('ms_id'),
                TextEntry::make('email'),
                // TextEntry::make('abn_secret'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('ms_id')->searchable()->sortable(),
                TextColumn::make('suscription_id'),
                ViewColumn::make('services')->view('filament.clusters.msgraph.columns.service-viewer'),
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
                    ->form(fn($record) => DynamicFormBuilder::build(config('msgraph.services'), $record))
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
                    ->action(fn(MsgUser $record) => $record->suscribe())
                    ->visible(fn(MsgUser $record): bool => $record->suscription_id === null),
                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(MsgUser $record) => $record->revokeSuscription())
                    ->visible(fn(MsgUser $record): bool => $record->suscription_id !== null),
                Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(fn(MsgUser $record) => $record->refreshSuscription())
                    ->visible(fn(MsgUser $record): bool => $record->suscription_id !== null),
            ])
            ->recordUrl(
                fn(MsgUser $record): string => MsgUserResource::getUrl('view', ['record' => $record])
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
            'create' => Pages\CreateMsgUser::route('/create'),
            'edit' => Pages\EditMsgUser::route('/{record}/edit'),
            'view' => Pages\ViewMsgUser::route('/{record}/view'),
        ];
    }
}
