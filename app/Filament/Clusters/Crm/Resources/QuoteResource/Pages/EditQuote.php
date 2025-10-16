<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Forms\Form;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms;
use App\Models\Quote;
use Filament\Infolists;
use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;
use App\Filament\Utils\StateUtils;
use App\Models\States\Quote\Draft;
use App\Models\States\Quote\Validated;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use App\Filament\ModelStates\StateAction;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;
use App\Services\Pdf\Filament\Actions\GeneratePdfDownload;
use App\Services\Pdf\Templates\Quote\QuoteBasePdfTemplate;
use App\Services\Pdf\Templates\Quote\QuoteDetailedPdfTemplate;
use App\Services\MsGraph\EmailDraft\Filament\Actions\GenerateMsGraphEmailDraft;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;
    use HasPreviewModal;

    protected $listeners = ['totalsUpdated' => 'refreshInfolist'];

    protected function getHeaderActions(): array
    {
        return [
            // Bouton Save en premier
            StateUtils::getStateSaveButton()->color('success'),
            QuoteResource::getDuplicateAction()->color('info'),
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
                    ->templates([
                        QuoteBasePdfTemplate::class,
                    ])
            ])->label('Produire')
                ->icon('fas-file-export')
                ->button()
                ->color('gray'),
            DeleteAction::make()->hidden(fn($record) => $record->state->isSaveHidden)
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('title')
                    ->label('Titre')
                    ->required(),
                MarkdownEditor::make('description')
                    ->label('Description du devis')
                    ->columnSpanFull(),
                ...QuoteResource::getItemsBuilderComponent(),
                Hidden::make('total_ht_br'),
                Hidden::make('total_ht'),
                Hidden::make('total_options'),
                Hidden::make('total_avant_options'),
                Hidden::make('total_jours'),
            ])->columns(2);
    }

    protected function getFormActions(): array
    {
        return [
            // Le bouton Save est maintenant dans le header
            StateUtils::getStateSaveButton()->color('success'),
            IaUtils::MistralCorrectionAction(static::$resource, $this->record->state->isSaveHidden)->color('info'),
            $this->getCancelFormAction()
        ];
    }

    public function refreshInfolist()
    {
        $data = $this->getRecord()->fill($this->form->getState());
        $this->infolist->record($data);
        // Ne pas appeler ->render() sur un Schema dans Filament v4
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
                                'contact_id' => $record->contact_id,
                                'end_at' => $record->end_at,
                            ])
                            ->schema([
                                Select::make('company_id')
                                    ->label('Client')
                                    ->relationship('company', 'title')
                                    ->searchable()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->disabled(true),

                                Select::make('contact_id')
                                    ->label('Contact')
                                    ->relationship(
                                        name: 'contact',
                                        titleAttribute: 'full_name',
                                        modifyQueryUsing: fn($query, $get) => $get('company_id') ? $query->where('company_id', $get('company_id')) : $query,
                                    )
                                    ->searchable(fn($get) => $get('company_id') ? false : true)
                                    ->required(),
                                DatePicker::make('end_at')
                                    ->label('Fin')
                                    ->default(now()->addMonth())
                                    ->required(),
                            ])
                            ->action(function (array $data, Quote $record): void {
                                $record->fill($data)->save();
                            })
                            ->slideOver(),
                    ])
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Code'),
                                TextEntry::make('state')
                                    ->label('État'),
                                TextEntry::make('version')
                                    ->label('Version'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('company.title')
                                    ->label('Client')
                                    ->url(fn($record): string => route('filament.admin.crm.resources.companies.edit', ['record' => $record->company])),
                                TextEntry::make('contact.full_name')
                                    ->label('Contact')
                                    ->url(fn($record): string => route('filament.admin.crm.resources.contacts.edit', ['record' => $record->contact])),

                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('total_ht_br')->money('EUR')
                                    ->label('Total Av remise'),
                                TextEntry::make('total_avant_options')->money('EUR')
                                    ->label('Total hors options'),
                                TextEntry::make('total_options')->money('EUR')
                                    ->label('Total options'),
                                TextEntry::make('total_ht')->money('EUR')
                                    ->label('Total HT'),
                                TextEntry::make('total_jours')
                                    ->label('Total jours')
                                    ->suffix(' j')
                                    ->numeric(decimalPlaces: 2),

                            ]),
                        ActionGroup::make([
                            Action::make('activate_v')
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
                            Action::make('create_v')
                                ->label('Nouvelle version')
                                ->action(function ($record, $component) {
                                    //\Log::info($this->form->getState());
                                    $data = $this->form->getState();
                                    $newRecord = $record->createNewVersion($data);
                                    return redirect()->to(QuoteResource::getUrl('edit', ['record' => $newRecord]));
                                })->color('success'),
                            Action::make('clean')
                                ->label('Nettoyer autres V')
                                ->action(function ($record) {
                                    $record->cleanUnactive();
                                })
                                ->color('danger')
                                ->disabled(fn($record) => !$record->is_retained || !($record->cleanUnactiveTest() > 0))
                        ])->buttonGroup(),
                    ]),


            ]);
    }
}
