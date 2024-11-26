<?php

namespace App\Filament\Clusters\MsGraph\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\MsgUserDraft;
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
use App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\Pages;
use App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\RelationManagers\MsgEmailDraftRelationManager;

class MsgDraftUserResource extends Resource
{
    protected static ?string $model = MsgUserDraft::class;

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
                TextColumn::make('subscription_id'),
                MailServiceColumn::make('services_options')->serviceType('email-draft'),
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
                    ->form(fn($record) => DynamicFormBuilder::build($record, 'email-draft', 'services_options'))
                    ->action(function (array $data, $record) {
                        foreach ($data as $field => $value) {
                            $record->{$field} = $value;
                        }
                        $record->save();
                    }),
                Action::make('subscribe')
                    ->label('Souscrire')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-envelope-open')
                    ->modalDescription('Activez le mode test au préalable, si vous ne voulez pas modifier le mail')
                    ->action(fn(MsgUserDraft $record) => $record->subscribe())
                    ->visible(fn(MsgUserDraft $record): bool => $record->subscription_id === null),
                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(MsgUserDraft $record) => $record->revokeSubscription())
                    ->visible(fn(MsgUserDraft $record): bool => $record->subscription_id !== null),
                Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(fn(MsgUserDraft $record) => $record->refreshSuscription())
                    ->visible(fn(MsgUserDraft $record): bool => $record->subscription_id !== null),
            ])
            ->recordUrl(
                fn(MsgUserDraft $record): string => MsgDraftUserResource::getUrl('edit', ['record' => $record])
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
            MsgEmailDraftRelationManager::class,
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
