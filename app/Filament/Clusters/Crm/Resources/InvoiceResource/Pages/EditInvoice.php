<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Forms\Form;
use App\Filament\Utils\PdfUtils;
use Filament\Actions\EditAction;
use Illuminate\Support\HtmlString;
use Filament\Resources\Pages\EditRecord;
use Guava\FilamentClusters\Forms\Cluster;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;
    use HasPreviewModal;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('duplicate')
                ->label('Dupliquer la facture')
                ->modalHeading('Dupliquer la facture')
                ->modalDescription(new HtmlString("Attention cette action permet de <b>dupliquer</b> une facture <br> l état sera réinitialisé "))
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
                }),

        ];
    }

    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            PdfUtils::CreateActionPdf('Générer facture', 'pdf.invoice.main')->icon('heroicon-o-document')
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
