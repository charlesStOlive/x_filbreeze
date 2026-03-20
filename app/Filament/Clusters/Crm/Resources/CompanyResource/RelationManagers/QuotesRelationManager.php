<?php

namespace App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers;

use App\Filament\Clusters\Crm\Resources\QuoteResource;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    protected static ?string $relatedResource = QuoteResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Utilise tous les champs, avec company_id visible mais désactivé
                ...QuoteResource::getContactAndCompanyFields(false),
                DatePicker::make('end_at')
                    ->label('Fin')
                    ->default(now()->addMonth())
                    ->required(),
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->columnSpanFull(),
                MarkdownEditor::make('description')
                    ->label('Description du devis')
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->fillForm([
                        'company_id' => $this->getOwnerRecord()->id,
                    ]),
            ]);
    }
}
