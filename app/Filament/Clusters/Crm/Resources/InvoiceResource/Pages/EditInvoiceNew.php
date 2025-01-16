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

use Filament\Infolists;
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
    protected static string $view = 'filament.templates.form-info-list';

    protected function getHeaderActions(): array
    {
        return [
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
                Forms\Components\TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->columnSpan(fn($record) => $record->state == 'draft' ? 1 : 2),
                Forms\Components\MarkdownEditor::make('description')
                    ->label('Description de la facture')
                    ->columnSpanFull(),
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
                Infolists\Components\Section::make('info')
                    ->headerActions([
                        Infolists\Components\Actions\Action::make('edit')
                            ->fillForm(fn($record): array => [
                                'company_id' => $record->company_id,
                                'contact_id' => $record->contact_id,
                                'modalite' => $record->modalite,
                                'tx_tva' => $record->tx_tva,
                            ])
                            ->form([
                                ...InvoiceResource::getContactAndCompanyFields(false),
                                Forms\Components\DatePicker::make('submited_at')
                                    ->label('Date de soumission')
                                    ->required()
                                    ->visible(fn($record) => $record->state == 'draft' ? false : true),
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
                            ])
                            ->action(function (array $data): void {
                                // ...
                            })
                            ->slideOver(),
                    ])
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->label('Code'),
                                Infolists\Components\TextEntry::make('state')
                                    ->label('État'),
                            ]),
                        Infolists\Components\TextEntry::make('modalite')
                            ->label('modalite'),
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_ht_br')
                                    ->label('Total avant remise HT')->money('EUR'),
                                Infolists\Components\TextEntry::make('total_ht')
                                    ->label('Total HT')->money('EUR'),
                            ]),
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('tx_tva')
                                    ->label('Taux TVA'),
                                Infolists\Components\TextEntry::make('tva')
                                    ->label('Montant TVA')->money('EUR'),
                            ]),
                        Infolists\Components\TextEntry::make('total_ttc')->money('EUR')
                            ->label('Total TTC'),
                    ])
            ]);
    }
}
