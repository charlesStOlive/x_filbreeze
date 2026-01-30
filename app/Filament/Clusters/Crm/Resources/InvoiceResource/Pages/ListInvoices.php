<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Creer une facture')
                ->label('Nouvelle facture')
                ->createAnother(false)
                ->successRedirectUrl(fn($record): string => InvoiceResource::getUrl('edit', ['record' => $record]))
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...InvoiceResource::getContactAndCompanyFields(),
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
            ])->columns(2);
    }
}
