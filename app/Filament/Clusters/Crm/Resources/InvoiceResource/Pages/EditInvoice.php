<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Forms\Form;
use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;
use Filament\Actions\EditAction;
use Illuminate\Support\HtmlString;
use App\Forms\Components\Diff2Html;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;
use Guava\FilamentClusters\Forms\Cluster;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;
use ValentinMorice\FilamentJsonColumn\FilamentJsonColumn;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;
    use HasPreviewModal;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            InvoiceResource::getDuplicateAction(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);
        \Log::info('handleRecordUpdate', $data);
        return $record;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->icon('far-floppy-disk'),
            PdfUtils::CreateActionPdf('facture', 'pdf.invoice.main'),
            IaUtils::MisrtalCorrectionAction(static::class),
            $this->getCancelFormAction(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    // Section principale
                    Forms\Components\Section::make('Edition')
                        ->schema([
                            Forms\Components\Section::make('Base (titre, description, date de validité)')
                                ->schema([
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
                                                ->selectablePlaceholder(false)
                                                ->options([
                                                    null => '0%',
                                                    '0.2' => '20%',
                                                ])
                                                ->default('0.2')
                                        ])->columns(2),
                                    ...InvoiceResource::getContactAndCompanyFields(false),
                                    Forms\Components\MarkdownEditor::make('description')
                                        ->label('Description de la facture')
                                        ->columnSpanFull(),
                                ])->collapsed(true)
                                ->columns(2),
                            ...InvoiceResource::getItemsBuilderComponent(),
                        ])->columnSpan(3)->compact(),
                    // Section latérale
                    Forms\Components\Section::make('Informations')
                        ->schema([
                            Cluster::make([
                                Forms\Components\TextInput::make('code')
                                    ->disabled(),
                                Forms\Components\TextInput::make('state')
                                    ->disabled(),
                            ])->label('Code / Etat'),
                            Cluster::make([
                                Forms\Components\TextInput::make('total_ht_br')
                                    ->label('Total avant remise HT')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('total_ht')
                                    ->numeric()
                                    ->disabled()
                                    ->inputMode('decimal')
                                    ->dehydrated()
                            ])->label("Total (AV/AP remise)"),
                            Cluster::make([
                                Forms\Components\TextInput::make('tx_tva')
                                    ->numeric()
                                    ->disabled()
                                    ->inputMode('decimal')
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('tva')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                            ])->label("Tx TVA / TVA"),


                            Forms\Components\TextInput::make('total_ttc')
                                ->label('Total TTC')
                                ->numeric()
                                ->inputMode('decimal')
                                ->disabled()
                                ->dehydrated(),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('validate')
                                    ->label('Valider facture')
                                    ->modalHeading(fn($record) => $record->total_ht == 0
                                        ? 'Validation impossible'
                                        : 'Valider cette facture')
                                    ->modalDescription(fn($record) => $record->total_ht == 0
                                        ? new HtmlString("Il est interdit de valider une facture dont le montant HT est égal à 0.")
                                        : new HtmlString("Attention <b>valider</b> une facture la verouille. <br> Avez-vous eu un BDC ?  êtes-vous certain de vouloir la faire ?"))
                                    ->form(fn($record) => $record->total_ht == 0
                                        ? []
                                        : [
                                            Forms\Components\DateTimePicker::make('submited_at')
                                                ->label('Validé le')
                                                ->default(now())
                                        ])
                                    ->action(function ($record, $data) {
                                        if ($record->total_ht == 0) {
                                            return;
                                        }
                                        \Log::info($this->data);
                                        $this->data['submited_at'] = $data['submited_at'];
                                        $this->data['state'] = 'validated';
                                        $record->fill($this->data);
                                        $record->save();
                                        return redirect()->to(static::$resource::getUrl('edit', ['record' => $record]));
                                    })
                                    ->hidden(fn($record) => !$record->state || $record->state !== 'draft')
                                    ->color(fn($record) => $record->total_ht == 0 ? 'danger' : 'success'),
                            ])->fullWidth(),
                        ])
                        ->compact()
                        ->columnSpan(1),
                ])->compact()->columns(4)->columnSpanFull(),
            ]);
    }
}
