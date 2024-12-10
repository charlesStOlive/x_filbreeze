<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Actions;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\Crm\Resources\QuoteResource;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalHeading('Creer un nouveau devis')
                ->label('Nouveau devis')
                ->createAnother(false)
                ->successRedirectUrl(fn($record): string => QuoteResource::getUrl('edit', ['record' => $record]))
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                ...QuoteResource::getContactAndCompanyFields(),
                Forms\Components\DatePicker::make('end_at')
                    ->label('Fin')
                    ->default(now()->addMonth())
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\MarkdownEditor::make('description')
                    ->label('Description du devis')
                    ->columnSpanFull(),
            ])->columns(3);
    }
}
