<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Infolists;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Filament\Utils\IaUtils;
use Filament\Actions\ActionGroup;
use App\Filament\Utils\StateUtils;
use App\Models\States\Invoice\Payed;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use App\Models\States\Invoice\Canceled;

use App\Models\States\Invoice\Submited;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use CharlesStOlive\FilamentStateFusionEnhanced\Actions\StateFusionAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\MarkdownEditor;
use App\Services\Pdf\Templates\Invoice\InvoiceComplete;


use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;
use App\Services\Pdf\Filament\Actions\GeneratePdfDownload;
use App\Services\Pdf\Templates\Invoice\InvoiceBasePdfTemplate;
use App\Services\Pdf\Templates\Invoice\InvoiceSummaryPdfTemplate;
use App\Services\MsGraph\EmailDraft\Templates\Invoice\InvoiceSummaryTemplate;
use App\Services\MsGraph\EmailDraft\Filament\Actions\GenerateMsGraphEmailDraft;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;
    use HasPreviewModal;


    protected $listeners = ['totalsUpdated' => 'refreshInfolist'];

    protected function getHeaderActions(): array
    {
        return [
            StateUtils::getStateSaveButton(),
            ActionGroup::make([
                StateFusionAction::make('state_submited')
                    ->transitionTo(Submited::class)
                    ->after(function ($record) {
                        return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $record]));
                    }),
                StateFusionAction::make('state_payed')
                    ->transitionTo(Payed::class)
                    ->after(function () {
                        return redirect()->to(InvoiceResource::getUrl('index'));
                    }),
                StateFusionAction::make('state_canceled')
                    ->transitionTo(Canceled::class)
                    ->after(function () {
                        return redirect()->to(InvoiceResource::getUrl('index'));
                    }),
            ])->label('Etats')
                ->icon('fas-code-branch')
                ->button()
                ->color('primary'),
            InvoiceResource::getDuplicateAction(),
            ActionGroup::make([
                GenerateMsGraphEmailDraft::make('generateEmailDraft')
                    ->templates([
                        InvoiceSummaryTemplate::class
                    ]),
                GeneratePdfDownload::make('downloadPdf')
                    ->templates([
                        InvoiceSummaryPdfTemplate::class,
                        InvoiceBasePdfTemplate::class,
                        InvoiceComplete::class,
                    ])
            ])->label('Produire')
                ->icon('fas-file-export')
                ->button()
                ->color('gray'),

        ];
    }

    protected function getFormActions(): array
    {
        return [
            StateUtils::getStateSaveButton(),
            IaUtils::MistralCorrectionAction(static::$resource, $this->record->state->isSaveHidden),
            ActionGroup::make([
                StateFusionAction::make('state_submited')
                    ->transitionTo(Submited::class)
                    ->after(function ($record) {
                        return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $record]));
                    }),
                StateFusionAction::make('state_payed')
                    ->transitionTo(Payed::class)
                    ->after(function () {
                        return redirect()->to(InvoiceResource::getUrl('index'));
                    }),
                StateFusionAction::make('state_canceled')
                    ->transitionTo(Canceled::class)
                    ->after(function () {
                        return redirect()->to(InvoiceResource::getUrl('index'));
                    }),
            ])->label('Etats')->icon('fas-code-branch')
                ->button()
                ->color('primary'),
            ActionGroup::make([
                GenerateMsGraphEmailDraft::make('generateEmailDraft'),
                GeneratePdfDownload::make('downloadPdf')
                    ->templates([
                        InvoiceSummaryPdfTemplate::class,
                        InvoiceBasePdfTemplate::class,
                        InvoiceComplete::class,
                    ])
            ])->label('Produire')
                ->icon('fas-file-export')
                ->button()
                ->color('gray'),
            $this->getCancelFormAction(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...InvoiceResource::getItemsBuilderComponent(),
                Hidden::make('total_ht_br'),
                Hidden::make('total_ht'),
                Hidden::make('tx_tva'),
                Hidden::make('tva'),
                Hidden::make('total_ttc'),


            ]);
    }

    public function refreshInfolist()
    {
        $data = $this->getRecord()->fill($this->form->getState());
        $this->infolist->record($data);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->getRecord())
            ->components([
                Section::make('info')
                    ->headerActions([
                        Action::make('edit')
                            ->fillForm(fn($record): array => [
                                'company_id' => $record->company_id,
                                'description' => $record->description,
                                'title' => $record->title,
                                'contact_id' => $record->contact_id,
                                'modalite' => $record->modalite,
                                'tx_tva' => $record->tx_tva,
                                'submited_at' => $record->submited_at,
                            ])
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        ...InvoiceResource::getContactAndCompanyFields(false),
                                        TextInput::make('title')
                                            ->label('Titre')
                                            ->required()
                                            ->columnSpan(fn($record) => $record->state == 'draft' ? 1 : 2),
                                        MarkdownEditor::make('description')
                                            ->label('Description de la facture')
                                            ->columnSpanFull(),
                                        DatePicker::make('submited_at')
                                            ->label('Date de soumission')
                                            ->required()
                                            ->visible(fn($record) => $record->state == 'draft' ? false : true)
                                            ->dehydrated(fn($state) => filled($state)),
                                        TextInput::make('modalite')
                                            ->label('Modalité')
                                            ->default('fin de mois')
                                            ->required(),
                                        Select::make('tx_tva')
                                            ->label('TVA')
                                            ->options([
                                                '0' => '0%',
                                                '0.2' => '20%',
                                            ])
                                            ->default('0.2')
                                            ->selectablePlaceholder(false)
                                    ])
                            ])
                            ->action(function (array $data, $record): void {
                                //\Log::info('Editing invoice', ['data' => $data]);
                                $record->update($data);
                            })
                            ->slideOver(),
                    ])
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Code'),
                                TextEntry::make('state')
                                    ->label('État')
                                    ->badge(),
                            ]),
                        TextEntry::make('modalite')
                            ->label('modalite'),
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
                    ])
            ])->columns(3);
    }
}