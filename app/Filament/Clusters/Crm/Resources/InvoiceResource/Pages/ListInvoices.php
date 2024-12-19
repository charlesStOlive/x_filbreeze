<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\ListRecords;
use Guava\FilamentClusters\Forms\Cluster;
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
                Forms\Components\TextInput::make('title')
                    ->label('Titre')
                    ->required(),
                Cluster::make()->label('modalité & TVA')
                    ->schema([
                        Forms\Components\TextInput::make('modalite')
                            ->label('Modalité')
                            ->default('fin de mois')
                            ->required(),
                        Forms\Components\Select::make('tx_tva')
                            ->label('TVA')
                            ->options([
                                0 => '0%',
                                0.2 => '20%',
                            ])
                            ->default(0.2)
                            ->selectablePlaceholder(false)
                    ])->columns(2),
                Forms\Components\MarkdownEditor::make('description')
                    ->label('Description facture')
                    ->columnSpanFull(),
            ])->columns(2);
    }
}
