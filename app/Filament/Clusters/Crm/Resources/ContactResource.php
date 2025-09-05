<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Str;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Clusters\Crm\Resources\ContactResource\Pages\ListContacts;
use App\Filament\Clusters\Crm\Resources\ContactResource\Pages\CreateContact;
use App\Filament\Clusters\Crm\Resources\ContactResource\Pages\EditContact;
use Filament\Forms;
use Filament\Tables;
use App\Models\Contact;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Components\Tables\DateColumn;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Clusters\Crm\Resources\ContactResource\Pages;
use App\Filament\Clusters\Crm\Resources\ContactResource\RelationManagers;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-s-user';

    protected static ?string $cluster = Crm::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('civ')
                    ->maxLength(255)
                    ->default('Mme/M.'),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Textarea::make('memo')
                    ->columnSpanFull(),
                Toggle::make('is_ex'),
                TextInput::make('company_id')
                    ->numeric(),
                TextInput::make('tel')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('linkedin_ext_id')
                    ->maxLength(255),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('civ')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('full_name')
                    ->description(fn ($record): string => Str::limit($record->company->title, 35))
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->searchable(),
                IconColumn::make('is_ex')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('company.title')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tel')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('linkedin_ext_id')
                    ->url(fn($record) => $record->linkedin_ext_id ? 'https://www.linkedin.com/in/' . $record->linkedin_ext_id : null)
                    ->openUrlInNewTab(),
                DateColumn::make('deleted_at')
                    ->toggleable(isToggledHiddenByDefault: true),
                DateColumn::make('created_at')
                    ->toggleable(isToggledHiddenByDefault: true),
                DateColumn::make('updated_at')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('full_name', 'asc')
            ->filters([
                TernaryFilter::make('is_ex')->label('Exemple')->default(false),
                SelectFilter::make('company')
                    ->label('Entreprise')
                    ->relationship('company', 'title'), // Assuming 'company' is a valid relationship
                Filter::make('linkedin_ext_id')
                    ->label('Has LinkedIn?')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('linkedin_ext_id')->where('linkedin_ext_id', '!=', ''))
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'edit' => EditContact::route('/{record}/edit'),
        ];
    }
}
