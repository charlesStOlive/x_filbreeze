<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Actions;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalHeading('Creer une facture')
                ->label('Nouvelle facture')
                ->createAnother(false)
                ->successRedirectUrl(fn($record): string => InvoiceResource::getUrl('edit', ['record' => $record]))
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                ...InvoiceResource::getContactAndCompanyFields(),
                Forms\Components\TextInput::make('modalite')
                    ->label('Modalité')
                    ->default('fin de mois')
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\MarkdownEditor::make('description')
                    ->label('Description facture')
                    ->columnSpanFull(),
            ])->columns(3);
    }
}
