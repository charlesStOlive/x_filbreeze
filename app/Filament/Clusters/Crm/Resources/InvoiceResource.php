<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Actions;
use App\Models\Contact;
use App\Models\Invoice;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Filament\Clusters\Crm;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\Builder;
use App\Filament\ModelStates\StateColumn;
use Filament\Tables\Actions\CreateAction;
use Guava\FilamentClusters\Forms\Cluster;
use Filament\Tables\Columns\Summarizers\Sum;
use App\Filament\Components\Tables\DateColumn;
use App\Filament\Components\Tables\DateTimeColumn;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;
use App\Filament\Clusters\Crm\Resources\InvoiceResource\RelationManagers;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-s-document-currency-dollar';

    protected static ?string $cluster = Crm::class;

    protected static $quoteCache = [];

    public static function getLabel(): string
    {
        return 'Factures clients';
    }


    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('company.name')
                    ->label('Client'),
                Group::make('submited_at_my')
                    ->label('Soumis Ans/Mois'),
                Group::make('payed_at_my')
                    ->label('Payement Ans/Mois'),

            ])
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->sortable()
                    ->description(fn($record): string => \Str::limit($record->title, 35))
                    ->searchable(['code', 'title']),
                StateColumn::make('state')
                    ->badge(),
                Tables\Columns\TextColumn::make('company.title')
                    ->sortable()
                    ->description(fn($record): string => $record->contact->full_name),
                DateColumn::make('submited_at')
                    ->sortable(),
                DateColumn::make('payed_at')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_ht')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()),
                Tables\Columns\TextColumn::make('total_ttc')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()),
                Tables\Columns\TextColumn::make('tva')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()),
                DateColumn::make('created_at')
                    ->toggleable(isToggledHiddenByDefault: true),
                DateColumn::make('updated_at')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getContactAndCompanyFields($companyEditable = true): array
    {
        return [
            Forms\Components\Select::make('contact_id')
                ->label('Contact')
                ->relationship(
                    name: 'contact',
                    titleAttribute: 'full_name',
                    modifyQueryUsing: fn($query, $get) => $get('company_id') ? $query->where('company_id', $get('company_id')) : $query,
                )
                ->searchable(fn($get) => $get('company_id') ? false : true)
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $contact = Contact::find($state);
                        if ($contact && $contact->company_id) {
                            $set('company_id', $contact->company_id);
                        }
                    }
                }),
            Forms\Components\Select::make('company_id')
                ->label('Client')
                ->relationship('company', 'title')
                ->searchable()
                ->required()
                ->reactive()
                ->disabled(!$companyEditable),

        ];
    }

    protected static function getQuote($quoteId)
    {
        if (!isset(self::$quoteCache[$quoteId])) {
            self::$quoteCache[$quoteId] = \App\Models\Quote::find($quoteId);
        }
        return self::$quoteCache[$quoteId];
    }

    protected static function updateQuoteFields($quote, callable $set, $record)
    {
        if ($quote) {
            $set('total_quote', $quote->total_ht);
            $set('total_quote_left', \App\Models\Invoice::getAmountLeft($quote, $record));
        }
    }

    protected static function generateMarkdownContent($quote, $facturation)
    {
        return view('filament.clusters.crm.resources.invoices.md.auto_description', compact('quote', 'facturation'))->render();
    }

    public static function getItemsBuilderComponent(): array
    {
        return [
            Forms\Components\Fieldset::make('Elements de la facture')
                ->schema([
                    Builder::make('items')
                        ->label(false)
                        ->addActionLabel('Ajouter un élément à la facture')
                        ->collapsed()
                        ->live()
                        ->cloneable()
                        ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateItemsTotal($set, $get, $livewire))
                        ->blocks([
                            self::getOnQuoteBlock(),
                            self::getForfaitBlock(),
                            self::getTasksBlock(),
                            self::getTMABlock(),
                            self::getRemiseBlock(),
                        ])
                        ->columnSpanFull(),
                ])
        ];
    }

    protected static function getForfaitBlock()
    {
        return Builder\Block::make('forfait')
            ->icon('fas-check-circle')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Forfait';
                }
                return sprintf('%s %s (%s €HT)', 'Forfait : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->live(onBlur: true)
            ])
            ->columns(3);
    }

    protected static function getTasksBlock()
    {
        return Builder\Block::make('tasks')
            ->icon('fas-calculator')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Taches';
                }
                return sprintf('%s %s (%s €HT)', 'Taches : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                Forms\Components\TextInput::make('cu')
                    ->label('Total U')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                Forms\Components\TextInput::make('qty')
                    ->label('Qty')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
            ])
            ->columns(3);
    }

    protected static function getTMABlock()
    {
        return Builder\Block::make('tma')
            ->icon('fas-ticket')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'TMA';
                }
                return sprintf('TMA du %s au %s ', $state['start_at'] ?? 'inc',  $state['end_at'] ?? 'inc');
            })
            ->schema([
                Forms\Components\TextInput::make('start_at')
                    ->label('Debut')
                    ->type('month'),
                Forms\Components\TextInput::make('end_at')
                    ->label('Fin')
                    ->type('month'),
                Forms\Components\TextInput::make('qty_total')
                    ->label('Nombre de ticket')
                    ->numeric(),
                Forms\Components\TextInput::make('qty_facturable')
                    ->label('Nombre de ticket facturable')
                    ->numeric(),
                Forms\Components\TextInput::make('qty')
                    ->label('Nombre Heures facturables')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                Forms\Components\TextInput::make('cu')
                    ->label('Cout heure')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateTaskTotal($set, $get, $livewire)),
                Forms\Components\TextInput::make('total')
                    ->label('total')
                    ->numeric(),
            ])
            ->columns(3);
    }

    protected static function getOnQuoteBlock()
    {
        return Forms\Components\Builder\Block::make('on_quote')
            ->icon('fas-file-invoice')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Depuis devis';
                }
                $quoteId = $state['quote_id'] ?? null;
                if (!$quoteId) {
                    return 'Depuis devis';
                }
                $quote = self::getQuote($quoteId);
                return sprintf('Facturation depuis devis %s montant %s', $quote->code, $state['total'] ?? 0);
            })
            ->schema([
                Forms\Components\Select::make('quote_id')
                    ->label('Select Quote')
                    ->options(function (callable $get) {
                        $companyId = $get('../../../company_id');
                        \Log::info('company_id : ' . $companyId);
                        return \App\Models\Quote::where('state', 'validated')
                            ->where('company_id', $companyId)
                            ->withRemainingAmount()
                            ->pluck('title', 'id');
                    })
                    ->required()
                    ->reactive()
                    ->afterStateHydrated(function ($state, callable $set, $record) {
                        if ($state) {
                            $quote = self::getQuote($state);
                            self::updateQuoteFields($quote, $set, $record);
                        }
                    })
                    ->afterStateUpdated(function ($state, callable $set, $record) {
                        \Log::info('state');
                        \Log::info($state);
                        if ($state) {
                            $quote = self::getQuote($state);
                            self::updateQuoteFields($quote, $set, $record);
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('total_quote')
                    ->label('Total Facturable')
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('total_quote_left')
                    ->label('Total Restant à facturer')
                    ->disabled()
                    ->dehydrated(),

                Forms\Components\TextInput::make('billing_percentage')
                    ->label('%')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->live(onBlur: true)
                    ->hintActions([
                        Forms\Components\Actions\Action::make('p_30')
                            ->label('30%')
                            ->action(function ($get, $set, $livewire) {
                                $set('total', round($get('total_quote') * 30 / 100, 2));
                                self::updateQuoteTotal($set, $get, $livewire, 'total');
                            }),
                        Forms\Components\Actions\Action::make('p_40')
                            ->label('40%')
                            ->action(function ($get, $set, $livewire) {
                                $set('total', round($get('total_quote') * 40 / 100, 2));
                                self::updateQuoteTotal($set, $get, $livewire, 'total');
                            }),
                        Forms\Components\Actions\Action::make('p_full')
                            ->label('fin')
                            ->action(function ($get, $set, $livewire) {
                                $set('total', $get('total_quote_left'));
                                self::updateQuoteTotal($set, $get, $livewire, 'total');
                            }),
                    ])
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateQuoteTotal($set, $get, $livewire, 'billing_percentage')),
                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->dehydrated()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(callable $set, callable $get, $livewire) => self::updateQuoteTotal($set, $get, $livewire, 'total'))
                    ->rule(function (callable $get) {
                        return 'lte:' . ($get('total_quote_left') ?? 0);
                    }),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('auto_create')
                        ->icon('heroicon-m-clipboard')
                        ->label('Auto remplir titre et description')
                        ->action(function ($get, $set) {
                            $quoteId = $get('quote_id');
                            if ($quoteId) {
                                $quote = self::getQuote($quoteId);
                                \Log::info($quote->toArray());
                                \Log::info($quote->code);
                                if ($quote) {
                                    $facturation = [
                                        'total_quote' =>  $get('total_quote'),
                                        'total_quote_left' =>  $get('total_quote_left'),
                                        'billing_percentage' =>  $get('billing_percentage'),
                                        'total' =>  $get('total')
                                    ];
                                    $markdownContent = self::generateMarkdownContent($quote, $facturation);
                                    $set('title', 'Facturation devis n°' . $quote->code);
                                    $set('description', $markdownContent);
                                }
                            }
                            return;
                        })
                ])->columnSpanFull(),
                Forms\Components\TextInput::make('title')
                    ->label('Titre élement')
                    ->required()
                    ->reactive()
                    ->columnSpanFull(),
                Forms\Components\MarkdownEditor::make('description')
                    ->label('Description élement')
                    ->columnSpanFull()
                    ->disableToolbarButtons([
                        'attachFiles',
                        'table',
                    ]),
            ])
            ->columns(4);
    }

    protected static function getRemiseBlock()
    {
        return Builder\Block::make('remise')
            ->icon('fas-percentage')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Remise';
                }
                return sprintf('%s %s (%s €HT)', 'Remise : ', $state['title'] ?? 'inc',  $state['total'] ?? 0);
            })
            ->schema([
                ...self::getBasicItemsField(),
                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->numeric()
                    ->live(onBlur: true),
            ])
            ->columns(2);
    }

    public static function getDuplicateAction(): Actions\Action
    {
        return Actions\Action::make('duplicate')
            ->label('Dupliquer')
            ->icon('heroicon-o-document-duplicate')
            ->modalHeading('Dupliquer la facture')
            ->modalDescription(new HtmlString("Attention cette action permet de <b>dupliquer</b> une facture <br> l'état sera réinitialisé "))
            ->fillForm(fn($record): array => [
                'client_id' => $record->client_id,
                'contact_id' => $record->contact_id,
                'title' => $record->title,
            ])
            ->form([
                ...InvoiceResource::getContactAndCompanyFields(),
                Forms\Components\TextInput::make('title')
                    ->label('Titre')
                    ->required(),
            ])
            ->action(function ($record, $data) {
                $newRecord = $record->createNewReplication($data);
                return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $newRecord]));
            });
    }

    public static function updateTaskTotal(callable $set, callable $get, $livewire)
    {
        $cu = $get('cu') ?? 0;
        $qty = $get('qty') ?? 0;
        $total = $cu * $qty;
        $set('total', $total);
        self::updateItemsTotal($set, $get, $livewire, true);
    }

    public static function updateQuoteTotal(callable $set, callable $get, $livewire, $fieldSrc)
    {
        $billingPercentage = $get('billing_percentage') ?? 0;
        $totalQuote = $get('total_quote') ?? 0;
        $total = $get('total') ?? 0;
        if ($fieldSrc == 'billing_percentage') {
            $total = round($totalQuote * $billingPercentage / 100, 2);
            $set('total', $total);
        } else if ($fieldSrc == 'total') {
            $billingPercentage = $totalQuote ? round(($total / $totalQuote) * 100, 2) : 0;
            $set('billing_percentage', $billingPercentage);
        }
        self::updateItemsTotal($set, $get, $livewire, true);
    }

    public static function updateItemsTotal(callable $set, callable $get, $livewire, $parent = false)
    {
        $items = $get('items') ?? [];
        $tx_tva = $get('tx_tva') ?? 0;
        if ($parent) {
            $items = $get('../../..') ?? [];
            $tx_tva = $get('../../../tx_tva') ?? 0;
        }
        $totals = collect($items)
            ->partition(fn($item) => $item['type'] === 'remise');

        // Calcule la somme des totaux des remises
        $totalRemise = $totals[0]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        // Calcule la somme des totaux des autres éléments
        $totalHtBr = $totals[1]
            ->map(fn($item) => $item['data']['total'] ?? 0)
            ->sum();

        // Mettre à jour total_ht_br
        $totalHt = $totalHtBr - $totalRemise;
        $tva = round($totalHt * $tx_tva, 2);
        $totalTTC = round($totalHt + $tva, 2);
        if ($parent) {
            $set('../../../total_ht_br', $totalHtBr);
            $set('../../../total_ht', $totalHt);
            $set('../../../tva', $tva);
            $set('../../../total_ttc', $totalTTC);
        } else {
            $set('total_ht_br', $totalHtBr);
            $set('total_ht', $totalHt);
            $set('tva', $tva);
            $set('total_ttc', $totalTTC);
        }
        $livewire->dispatch('totalsUpdated');
    }

    public static function getBasicItemsField()
    {
        return [
            Forms\Components\TextInput::make('title')
                ->label('Titre élement')
                ->required()
                ->reactive()
                ->columnSpanFull(),
            Forms\Components\MarkdownEditor::make('description')
                ->label('Description élement')
                ->columnSpanFull()
                ->disableToolbarButtons([
                    'attachFiles',
                    'table',
                ])

        ];
    }




    public static function getRelations(): array
    {
        return [];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'edit' => Pages\EditInvoiceNew::route('/{record}/edit'),
            'preview-pdf' => Pages\PreviewPdf::route('/{record}/preview-pdf'),
        ];
    }
}
