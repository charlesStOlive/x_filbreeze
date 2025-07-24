<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Forms;
use App\Models\Quote;
use Filament\Actions;
use Filament\Infolists;
use Filament\Forms\Form;
use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;
use Filament\Infolists\Infolist;
use App\Filament\Utils\StateUtils;
use App\Models\States\Quote\Draft;
use App\Models\States\Quote\Validated;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use App\Filament\ModelStates\StateAction;
use Guava\FilamentClusters\Forms\Cluster;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;
use App\Services\Pdf\Filament\Actions\GeneratePdfDownload;
use App\Services\MsGraph\EmailDraft\Filament\Actions\GenerateMsGraphEmailDraft;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;
    use HasPreviewModal;

    protected $listeners = ['totalsUpdated' => 'refreshInfolist'];
    protected static string $view = 'filament.templates.form-info-list';

    protected function getHeaderActions(): array
    {
        return [
            QuoteResource::getDuplicateAction(),
            ActionGroup::make([
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
            ])->label('Etats')
                ->icon('fas-code-branch')
                ->button()
                ->color('primary'),

            ActionGroup::make([
                GenerateMsGraphEmailDraft::make('generateEmailDraft'),
                GeneratePdfDownload::make('downloadPdf')
            ])->label('Produire')
                ->icon('fas-file-export')
                ->button()
                ->color('gray'),
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
                Forms\Components\Hidden::make('total_options'),
                Forms\Components\Hidden::make('total_avant_options'),
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
                                Forms\Components\Select::make('company_id')
                                    ->label('Client')
                                    ->relationship('company', 'title')
                                    ->searchable()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->disabled(true),

                                Forms\Components\Select::make('contact_id')
                                    ->label('Contact')
                                    ->relationship(
                                        name: 'contact',
                                        titleAttribute: 'full_name',
                                        modifyQueryUsing: fn($query, $get) => $get('company_id') ? $query->where('company_id', $get('company_id')) : $query,
                                    )
                                    ->searchable(fn($get) => $get('company_id') ? false : true)
                                    ->required(),
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
                                Infolists\Components\TextEntry::make('total_avant_options')->money('EUR')
                                    ->label('Total hors options'),
                                Infolists\Components\TextEntry::make('total_options')->money('EUR')
                                    ->label('Total options'),
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
                                    //\Log::info($this->form->getState());
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
