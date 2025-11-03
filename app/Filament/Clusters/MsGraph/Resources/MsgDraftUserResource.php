<?php

namespace App\Filament\Clusters\MsGraph\Resources;

use Filament\Tables\Table;
use App\Models\MsgUserDraft;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;

use App\Filament\Clusters\MsGraph;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

use Filament\Forms\Components\TextInput;

use App\Filament\Components\Tables\MailServiceColumn;

use App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\Pages;
use App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\Pages\EditMsgUser;
use App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\Pages\ListMsgUsers;
use App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\RelationManagers\MsgEmailDraftRelationManager;

class MsgDraftUserResource extends Resource
{
    protected static ?string $model = MsgUserDraft::class;

    protected static string | \BackedEnum | null $navigationIcon = 'fas-right-from-bracket';

    protected static ?string $cluster = MsGraph::class;

    public static function getLabel(): string
    {
        return 'utilisateurs email brouillons';
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
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('ms_id')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subscription_id')->toggleable(isToggledHiddenByDefault: true),
                MailServiceColumn::make('services_options_edit')
                    ->label('Services (éditer)')
                    ->serviceType('email-draft')
                    ->openMode('edit')
                    ->disabledClick(),
                TextColumn::make('user.name')
                    ->label('Utilisateur lié'),
                //
            ])
            ->filters([
                Filter::make('is_test')
                    ->toggle()
                    ->query(fn($query) => $query->where('is_test', true)),
            ])
            ->recordActions([
                // ✅ Actions editServices et showServices supprimées - remplacées par MailServiceCell Livewire
                Action::make('subscribe')
                    ->tooltip('Activer abonnement aux services')
                    ->requiresConfirmation()
                    ->icon('heroicon-s-envelope-open')
                    ->iconButton()
                    ->modalDescription('Activez le mode test au préalable, si vous ne voulez pas modifier le mail')
                    ->action(fn(MsgUserDraft $record) => $record->subscribe())
                    ->visible(fn(MsgUserDraft $record): bool => $record->subscription_id === null),
                Action::make('revoke')
                    ->tooltip('Révoquer abonnement aux services')
                    ->icon('heroicon-s-x-circle')
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(MsgUserDraft $record) => $record->revokeSubscription())
                    ->visible(fn(MsgUserDraft $record): bool => $record->subscription_id !== null),
                Action::make('refresh')
                    ->tooltip('Refresh abonnement')
                    ->icon('heroicon-s-arrow-path')
                    ->iconButton()
                    ->color('gray')
                    ->action(fn(MsgUserDraft $record) => $record->refreshSubscription())
                    ->visible(fn(MsgUserDraft $record): bool => $record->subscription_id !== null),
                Action::make('toggleUserLink')
                    ->tooltip(fn(MsgUserDraft $record) => $record->user_id ? 'Délier l\'utilisateur' : 'Lier à un utilisateur')
                    ->icon(fn(MsgUserDraft $record) => $record->user_id ? 'heroicon-s-user-minus' : 'heroicon-s-user-plus')
                    ->iconButton()
                    ->color(fn(MsgUserDraft $record) => $record->user_id ? 'danger' : 'gray')
                    ->requiresConfirmation()
                    ->action(fn(MsgUserDraft $record) => $record->toggleUserLink())
            ])
            ->recordUrl(
                fn(MsgUserDraft $record): string => MsgDraftUserResource::getUrl('edit', ['record' => $record])
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
            MsgEmailDraftRelationManager::class,
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
