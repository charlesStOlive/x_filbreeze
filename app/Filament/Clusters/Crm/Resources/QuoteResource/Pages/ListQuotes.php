<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Actions;
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
            CreateAction::make()
                ->modalHeading('Creer un nouveau devis')
                ->label('Nouveau devis')
                ->createAnother(false)
                ->successRedirectUrl(fn($record): string => QuoteResource::getUrl('edit', ['record' => $record]))
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...QuoteResource::getContactAndCompanyFields(),
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
            ])->columns(3);
    }
}
