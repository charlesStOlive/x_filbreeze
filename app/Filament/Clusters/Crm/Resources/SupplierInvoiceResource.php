<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use App\Models\SupplierInvoice;
use Filament\Resources\Resource;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\RelationManagers;


class SupplierInvoiceResource extends Resource
{
    protected static ?string $model = SupplierInvoice::class;

    protected static ?string $cluster = Crm::class;

    public static function getNavigationLabel(): string
    {
        return __('crm.supplier_invoice');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->label('Supplier'),
                Forms\Components\TextInput::make('invoice_number')->required()->label('Invoice Number'),
                Forms\Components\DatePicker::make('invoice_date')->required()->label('Invoice Date'),
                Forms\Components\TextInput::make('total_amount')
                    ->numeric()
                    ->required()
                    ->label('Total Amount'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                    ])
                    ->default('pending')
                    ->label('Status'),
                Forms\Components\Textarea::make('notes')->label('Notes'),
                SpatieMediaLibraryFileUpload::make('pdf_invoice')
                    ->collection('invoices')
                    ->acceptedFileTypes(['application/pdf'])
                    ->label('Invoice PDF')
                    ->maxFiles(1), // Ensures only one file can be uploaded
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')->label('Supplier')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('invoice_number')->label('Invoice Number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('invoice_date')->label('Invoice Date')->dateTime(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total Amount')->money('usd'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->colors([
                        'secondary' => 'pending',
                        'success' => 'paid',
                        'danger' => 'overdue',
                    ]),
            ])
            ->filters([
                // Add any filters if necessary
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierInvoices::route('/'),
            'create' => Pages\CreateSupplierInvoice::route('/create'),
            'edit' => Pages\EditSupplierInvoice::route('/{record}/edit'),
        ];
    }
}
