<?php 

namespace App\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ViewColumn;
use App\Filament\Components\Tables\MailResultColumn;
use App\Filament\Components\Tables\MailServiceColumn;
use App\Filament\Components\Tables\DateTimeColumn;
use Filament\Resources\RelationManagers\RelationManager;

class MsgEmailDraftRelationManager extends RelationManager
{
    protected static string $relationship = 'msg_email_drafts';

    protected static ?string $title = 'Emails';

    protected $listeners = ['refreshMsgEmailDraftsRelationManager' => '$refresh'];

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->label('Sujet')->limit(50)->sortable()->searchable(),
                TextColumn::make('status')->label('Etat'),
                DateTimeColumn::make('created_at')->label('Crée le'),
                MailServiceColumn::make('services_options')->serviceType('email-draft'),
                MailResultColumn::make('services_results')->serviceType('email-draft'),
            ])
            ->filters([
                //En attente
            ])
            ->selectable(true)
            ->toolbarActions([
                    DeleteBulkAction::make(),
            ]);
    }
}
