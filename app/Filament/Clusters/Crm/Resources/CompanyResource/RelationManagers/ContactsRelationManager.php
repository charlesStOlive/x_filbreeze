<?php

namespace App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('first_name')
                ->label('Prénom')
                ->required()
                ->maxLength(255),

            TextInput::make('last_name')
                ->label('Nom')
                ->required()
                ->maxLength(255),

            Select::make('civ')
                ->label('Civilité')
                ->options([
                    'Mme' => 'Mme',
                    'M.' => 'M.',
                    'Mx' => 'Mx',
                    'Mme/M.' => 'Mme/M.',
                ])
                ->default('Mme/M.'),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required(),

            TextInput::make('tel')
                ->label('Téléphone'),

            Textarea::make('memo')
                ->label('Mémo')
                ->columnSpanFull(),

            Toggle::make('is_ex')
                ->label('Externe ?')
                ->default(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('tel')
                    ->label('Téléphone'),

                IconColumn::make('is_ex')
                    ->label('Externe ?')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_ex')->label('Externe ?'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
