<?php 

namespace App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;

class MsgEmailDraftRelationManager extends RelationManager
{
    protected static string $relationship = 'msg_email_drafts';

    protected $listeners = ['refreshMsgEmailDraftsRelationManager' => '$refresh'];

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from')->label('De')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('subject')->label('Sujet')->limit(50)->sortable()->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Crée le')->dateTime()->timezone('Europe/Paris')->sortable(),
                ViewColumn::make('services')->view('filament.clusters.msgraph.columns.service-viewer'),
                ViewColumn::make('results')->view('filament.clusters.msgraph.columns.results-viewer'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //En attente
            ])
            ->selectable(true)
            ->bulkActions([
                    DeleteBulkAction::make(),
            ]);
    }
}
