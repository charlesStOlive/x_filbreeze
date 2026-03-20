<?php

namespace App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers;

use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $relatedResource = InvoiceResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Utilise tous les champs, avec company_id visible mais désactivé
                ...InvoiceResource::getContactAndCompanyFields(false),
                TextInput::make('title')
                    ->label('Titre')
                    ->required(),
                Group::make([
                    TextInput::make('modalite')
                        ->label('Modalité')
                        ->default('fin de mois')
                        ->required(),
                    Select::make('tx_tva')
                        ->label('TVA')
                        ->options([
                            '0' => '0%',
                            '0.2' => '20%',
                        ])
                        ->default('0.2')
                        ->selectablePlaceholder(false)
                ])->columns(2),
                MarkdownEditor::make('description')
                    ->label('Description facture')
                    ->columnSpanFull(),
            ])
            ->columns(2);
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
