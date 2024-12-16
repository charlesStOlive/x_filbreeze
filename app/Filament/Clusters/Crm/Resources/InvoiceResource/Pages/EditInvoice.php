<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Forms\Form;
use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;
use Filament\Actions\EditAction;
use Illuminate\Support\HtmlString;
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

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            PdfUtils::CreateActionPdf('facture', 'pdf.invoice.main'),

            Actions\Action::make('Orthographes')
                ->icon('fas-wand-sparkles')
                ->fillForm(function ($data, $record, $livewire) {
                    $dataToSend = $record->extractTextToJson();
                    return [
                        'data_for_ia' => $dataToSend,
                    ];
                })
                ->form([
                    Forms\Components\Wizard::make([
                        // Étape 1 : 
                        Forms\Components\Wizard\Step::make('Données à corriger')
                            ->schema([
                                FilamentJsonColumn::make('data_for_ia'),
                            ])
                            ->afterValidation(function ($get, $set) {
                                $dataCorrected = static::callMistralAgent(json_encode($get('data_for_ia')));
                                // $dataCorrected = '{"title":"Ceci est un test de correction d\'orthographes","description":"Une simulation de correction d\'un devis qui a des erreurs d\'orthographe. \\n* Il doit conserver la mise en forme normalement\\n* Il faut que je trouve un moyen de comparer avant et après","items":[{"data":{"title":"Une première ligne","description":"Voici est mon contenu de la facture. \\nIl possède du texte et du **markdown**"}},{"data":{"title":"Une autre ligne","description":"Là aussi j\'ai une autre ligne. \\n"}},{"data":{"title":"remise exceptionnelle de 500 €","description":"Parce que vous le valez bien une réduction. "}}]}';
                                $set('data_corrected', $dataCorrected);
                            }),

                        // Étape 2 : Vérification des informations
                        Forms\Components\Wizard\Step::make('Vérifier les informations')
                            ->schema([
                                FilamentJsonColumn::make('data_for_ia'),
                                FilamentJsonColumn::make('data_corrected'),
                            ])->columns(2)
                            ->afterValidation(function ($get, $record)   {
                                $dataCorrected = $get('data_corrected');
                                \Log::info($dataCorrected);
                                $dataCorrected = json_decode($dataCorrected, true);
                                \Log::info($dataCorrected);
                                $record->injectTextFromJson($dataCorrected);
                                $record->save();
                                return redirect()->to(self::$resource::getUrl('edit', ['record' => $record]));

                            }),

                        // Étape 3 : Confirmation
                        Forms\Components\Wizard\Step::make('Confirmation')
                            ->schema([])
                    ])
                ])->modalWidth('7xl'),
                $this->getCancelFormAction(),
        ];
    }

    public static function callMistralAgent(string $mistralPrompt): string
    {
        $mistralAgent = new \App\Services\Ia\MistralAgentService(); // Instanciation directe
        $agentId = 'ag:3e2c948d:20241213:correction-ortographe:b3c27f0b';
        $response = $mistralAgent->callAgent($agentId, $mistralPrompt);
        return $response['choices'][0]['message']['content'] ?? '';
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
                                    Forms\Components\TextInput::make('modalite')
                                        ->label('Modalité')
                                        ->default('fin de mois')
                                        ->required(),
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
                                Forms\Components\TextInput::make('status')
                                    ->disabled(),
                            ])->label('Code / Etat'),
                            Cluster::make([
                                Forms\Components\TextInput::make('total_ht_br')
                                    ->label('Total avant remise HT')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('total_ht')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                            ])->label("Total (AV/AP remise)"),
                            Cluster::make([
                                Forms\Components\TextInput::make('tx_tva')
                                    ->numeric()
                                    ->length(8)
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('tva')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                            ])->label("Tx TVA / TVA"),


                            Forms\Components\TextInput::make('total_ttc')
                                ->label('Total TTC')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('validate')
                                    ->label('Valider ce devis')
                                    ->modalHeading(fn($record) => $record->total_ht == 0
                                        ? 'Validation impossible'
                                        : 'Valider ce devis')
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

                                        $record->submited_at = $data['submited_at'];
                                        $record->status = 'validated';
                                        $record->save();

                                        return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $record]));
                                    })
                                    ->modalSubmitAction(fn($record) => $record->total_ht == 0 ? false : true)
                                    ->hidden(fn($record) => !$record->is_retained || $record->status === 'validated')
                                    ->color(fn($record) => $record->total_ht == 0 ? 'danger' : 'success'),
                            ])->fullWidth(),
                        ])
                        ->compact()
                        ->columnSpan(1),
                ])->compact()->columns(4)->columnSpanFull(),
            ]);
    }
}
