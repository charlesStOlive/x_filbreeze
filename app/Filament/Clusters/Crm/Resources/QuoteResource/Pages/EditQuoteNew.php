<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Forms;
use App\Models\Quote;
use Filament\Actions;
use Filament\Forms\Form;
use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Filament\Utils\StateUtils;
use App\Models\States\Quote\Draft;
use App\Models\States\Quote\Validated;
use Filament\Resources\Pages\EditRecord;
use App\Filament\ModelStates\StateAction;
use Guava\FilamentClusters\Forms\Cluster;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;

class EditQuoteNew extends EditRecord
{
    protected static string $resource = QuoteResource::class;
    use HasPreviewModal;

    protected $listeners = ['totalsUpdated' => 'refreshInfolist'];
    protected static string $view = 'filament.templates.form-info-list';

    protected function getHeaderActions(): array
    {
        return [
            QuoteResource::getDuplicateAction(),
            PdfUtils::CreateActionPdf('devis', 'pdf.quote.main'),
            StateAction::make('a_valide')
                ->before(function ($record) {
                    $record->fill($this->data);
                })
                ->transitionTo(Validated::class)
                ->after(function ($record) {
                    return redirect()->to(QuoteResource::getUrl('edit', ['record' => $record]));
                })->disabled(fn($record) => !$record->is_retained)
                ->label(fn($record) => !$record->is_retained ? 'Activer dabord le devis' : 'Valider ce devis'),
            StateAction::make('a_delete')
                ->transitionTo(Draft::class),
            Actions\DeleteAction::make()->hidden(fn($record) => $record->state->isSaveHidden)
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titre')
                    ->required(),
                Forms\Components\MarkdownEditor::make('description')
                    ->label('Description du devis')
                    ->columnSpanFull(),
                ...QuoteResource::getItemsBuilderComponent(),
                Forms\Components\Hidden::make('total_ht_br'),
                Forms\Components\Hidden::make('total_ht'),
            ])->columns(2);
    }

    protected function getFormActions(): array
    {
        return [
            StateUtils::getStateSaveButton(),
            PdfUtils::CreateActionPdf('devis', 'pdf.quote.main'),
            IaUtils::MisrtalCorrectionAction(static::$resource, $this->record->state->isSaveHidden),
            $this->getCancelFormAction()
        ];
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
                                'end_at' => $record->end_at,
                            ])
                            ->form([
                                ...QuoteResource::getContactAndCompanyFields(false),
                                Forms\Components\DatePicker::make('end_at')
                                    ->label('Fin')
                                    ->default(now()->addMonth())
                                    ->required(),
                            ])
                            ->action(function (array $data): void {
                                // ...
                            })
                            ->slideOver(),
                    ])
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->label('Code'),
                                Infolists\Components\TextEntry::make('state')
                                    ->label('État'),
                                Infolists\Components\TextEntry::make('version')
                                    ->label('Version'),
                            ]),
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('company.title')
                                    ->label('Client')
                                    ->url(fn($record): string => route('filament.admin.crm.resources.companies.edit', ['record' => $record->company])),
                                Infolists\Components\TextEntry::make('contact.full_name')
                                    ->label('Contact')
                                    ->url(fn($record): string => route('filament.admin.crm.resources.contacts.edit', ['record' => $record->contact])),

                            ]),
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_ht_br')->money('EUR')
                                    ->label('Total Av remise'),
                                Infolists\Components\TextEntry::make('total_ht')->money('EUR')
                                    ->label('Total HT'),

                            ]),
                        Infolists\Components\Actions::make([
                            Infolists\Components\Actions\Action::make('activate_v')
                                ->label('Activer ce devis')
                                ->hidden(function ($record) {
                                    $validatedExist = $record->hasOneVersionValidated();
                                    $alreadyActive = $record->is_retained;
                                    return $validatedExist || $alreadyActive;
                                })
                                ->action(function ($record) {
                                    $record->swapRetainedQuote();
                                })
                                ->color('success'),
                            Infolists\Components\Actions\Action::make('create_v')
                                ->label('Nouvelle version')
                                ->action(function ($record, $component) {
                                    \Log::info($this->form->getState());
                                    $data = $this->form->getState();
                                    $newRecord = $record->createNewVersion($data);
                                    return redirect()->to(QuoteResource::getUrl('edit', ['record' => $newRecord]));
                                }),
                            Infolists\Components\Actions\Action::make('clean')
                                ->label('Nettoyer autres V')
                                ->action(function ($record) {
                                    $record->cleanUnactive();
                                })
                                ->disabled(fn($record) => !$record->is_retained || !($record->cleanUnactiveTest() > 0))
                        ])->fullWidth(),
                    ]),


            ]);
    }
}
