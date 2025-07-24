<?php

namespace App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('first_name')
                ->label('Prénom')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('last_name')
                ->label('Nom')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('civ')
                ->label('Civilité')
                ->options([
                    'Mme' => 'Mme',
                    'M.' => 'M.',
                    'Mx' => 'Mx',
                    'Mme/M.' => 'Mme/M.',
                ])
                ->default('Mme/M.'),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required(),

            Forms\Components\TextInput::make('tel')
                ->label('Téléphone'),

            Forms\Components\Textarea::make('memo')
                ->label('Mémo')
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_ex')
                ->label('Externe ?')
                ->default(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('tel')
                    ->label('Téléphone'),

                Tables\Columns\IconColumn::make('is_ex')
                    ->label('Externe ?')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_ex')->label('Externe ?'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
