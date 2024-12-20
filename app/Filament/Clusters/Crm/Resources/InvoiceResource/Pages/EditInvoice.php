<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Forms\Form;
use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;

use App\Filament\Utils\StateUtils;
use App\Models\States\Invoice\Draft;
use App\Models\States\Invoice\Payed;
use App\Models\States\Invoice\Submited;
use Illuminate\Database\Eloquent\Model;

use App\Filament\ModelStates\StateRadio;
use Filament\Resources\Pages\EditRecord;
use App\Filament\ModelStates\StateAction;
use Guava\FilamentClusters\Forms\Cluster;
use Illuminate\Validation\ValidationException;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;
use ValentinMorice\FilamentJsonColumn\FilamentJsonColumn;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;
    use HasPreviewModal;

    // protected function onValidationError(ValidationException $exception): void
    // {
    //     Notification::make()
    //         ->title('Erreur de validation')
    //         ->body($exception->validator->errors()->first()) // Affiche le premier message d'erreur
    //         ->danger()
    //         ->send();
    // }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            InvoiceResource::getDuplicateAction(),
            StateAction::make('state')
                ->before(function ($record) {
                    $record->fill($this->data);
                })
                ->transitionTo(Submited::class)
                ->after(function ($record) {
                    return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $record]));
                })->disabled(fn() => $this->data['total_ht'] > 0 ? false : true),
            StateAction::make('state')
                ->transitionTo(Payed::class)
                ->after(function ($record) {
                    return redirect()->to(InvoiceResource::getUrl('index'));
                }),

        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    protected function getFormActions(): array
    {


        return [
            StateUtils::getStateSaveButton(),
            PdfUtils::CreateActionPdf('facture', 'pdf.invoice.main'),
            IaUtils::MisrtalCorrectionAction(static::$resource, $this->record->state->isSaveHidden),
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
                                                ->options([
                                                    0 => '0%',
                                                    0.2 => '20%',
                                                ])
                                                ->default(0.2)
                                                ->selectablePlaceholder(false)
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
                            ])->fullWidth(),
                        ])
                        ->compact()
                        ->columnSpan(1),
                ])->compact()->columns(4)->columnSpanFull(),
            ]);
    }
}
