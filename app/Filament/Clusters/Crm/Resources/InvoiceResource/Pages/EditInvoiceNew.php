<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Forms\Form;
use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;

use Filament\Infolists\Infolist;
use App\Filament\Utils\StateUtils;
use App\Models\States\Invoice\Draft;
use App\Models\States\Invoice\Payed;
use App\Models\States\Invoice\Submited;

use Filament\Infolists\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;
use App\Filament\ModelStates\StateAction;
use Guava\FilamentClusters\Forms\Cluster;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;

class EditInvoiceNew extends EditRecord
{
    protected static string $resource = InvoiceResource::class;
    use HasPreviewModal;
    

    protected $listeners = ['totalsUpdated' => 'refreshInfolist'];

    protected $total_ht_br;

    protected static string $view = 'filament.clusters.crm.pages.form-info-list';

    protected function getHeaderActions(): array
    {
        return [
            $this->getSubmitFormAction(),
            Actions\DeleteAction::make(),
            InvoiceResource::getDuplicateAction(),
            StateAction::make('state_s')
                ->before(function ($record) {
                    $record->fill($this->data);
                })
                ->transitionTo(Submited::class)
                ->after(function ($record) {
                    return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $record]));
                })->disabled(fn() => $this->data['total_ht'] > 0 ? false : true),
            StateAction::make('state_p')
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
            $this->getSubmitFormAction(),
            PdfUtils::CreateActionPdf('facture', 'pdf.invoice.main'),
            IaUtils::MisrtalCorrectionAction(static::$resource, $this->record->state->isSaveHidden),
            $this->getCancelFormAction(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Base (titre, description, date de validité)')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->columnSpan(fn($record) => $record->state == 'draft' ? 1 : 2),
                        Forms\Components\DatePicker::make('submited_at')
                            ->label('Date de soumission')
                            ->required()
                            ->visible(fn($record) => $record->state == 'draft' ? false : true),
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
                    ])->collapsed(true),
                ...InvoiceResource::getItemsBuilderComponent(),
                Forms\Components\Hidden::make('total_ht_br'),
                Forms\Components\Hidden::make('total_ht'),
                Forms\Components\Hidden::make('tx_tva'),
                Forms\Components\Hidden::make('tva'),
                Forms\Components\Hidden::make('total_ttc'),


            ]);
    }

    public function refreshInfolist()
    {
        $data = $this->getRecord()->fill($this->form->getState());
        $this->infolist->record($data)->render();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code'),
                        TextEntry::make('state')
                            ->label('État'),
                    ]),
                Grid::make(2)
                    ->schema([
                        TextEntry::make('total_ht_br')
                            ->label('Total avant remise HT')->money('EUR'),
                        TextEntry::make('total_ht')
                            ->label('Total HT')->money('EUR'),
                    ]),
                Grid::make(2)
                    ->schema([
                        TextEntry::make('tx_tva')
                            ->label('Taux TVA'),
                        TextEntry::make('tva')
                            ->label('Montant TVA')->money('EUR'),
                    ]),
                TextEntry::make('total_ttc')->money('EUR')
                    ->label('Total TTC'),
            ]);
    }
}
