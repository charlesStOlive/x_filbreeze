<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Forms;
use App\Models\Quote;
use Filament\Infolists;
use Filament\Forms\Form;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Filament\Utils\IaUtils;
use Filament\Actions\ActionGroup;
use App\Filament\Utils\StateUtils;
use App\Models\States\Quote\Draft;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use App\Models\States\Quote\Validated;
use App\Models\States\Quote\QuoteState;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Group;
use CharlesStOlive\FilamentUi\Split\SidePane;
use CharlesStOlive\FilamentUi\Split\HasSidePane;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\MarkdownEditor;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use CharlesStOlive\FilamentStateFusionEnhanced\Actions\StateFusionAction;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;
use App\Services\Pdf\Filament\Actions\GeneratePdfDownload;
use App\Services\Pdf\Templates\Quote\QuoteBasePdfTemplate;
use CharlesStOlive\FilamentStateFusionEnhanced\Actions\StateFusionActionGroup;
use App\Services\Pdf\Templates\Quote\QuoteDetailedPdfTemplate;
use App\Services\MsGraph\EmailDraft\Filament\Actions\GenerateMsGraphEmailDraft;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;
    use HasPreviewModal;
    use HasSidePane;

    protected $listeners = ['totalsUpdated' => 'refreshInfolist'];

    /**
     * L'infolist dans le volet latéral de filament-ui : ouvert en permanence (sans croix), à 25 % de la
     * largeur par défaut, avec l'action de modification dans son bandeau.
     *
     * @return array<string, SidePane>
     */
    protected function getSidePanes(): array
    {
        return [
            'info' => SidePane::make('info')
                ->label('Infos')
                ->icon('heroicon-o-information-circle')
                ->headerActions([$this->getInfoEditAction()])
                ->closable(false)
                ->schema([Group::make([EmbeddedSchema::make('infolist')])->extraAttributes(['class' => 'p-4'])]),
        ];
    }

    protected function getDefaultSidePane(): ?string
    {
        return 'info';
    }

    protected function getSidePaneWidth(): int
    {
        return 25;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Bouton Save en premier
            StateUtils::getStateSaveButton()->color('success'),
            QuoteResource::getDuplicateAction()->color('info'),
            // ActionGroup::make([
            //     StateFusionAction::make('a_valide')
            //         ->before(function ($record) {
            //             $record->fill($this->data);
            //         })
            //         ->transitionTo(Validated::class)
            //         ->after(function ($record) {
            //             return redirect()->to(QuoteResource::getUrl('edit', ['record' => $record]));
            //         })->disabled(fn($record) => !$record->is_retained)
            //         ->label(fn($record) => !$record->is_retained ? 'Activer dabord le devis' : 'Valider ce devis'),
            //     StateFusionAction::make('a_delete')
            //         ->transitionTo(Draft::class),
            
            // ])->label('Etats')
            //     ->icon('fas-code-branch')
            //     ->button()
            //     ->color('primary'),
            StateFusionActionGroup::generate('state', QuoteState::class)
                ->label('Changer état')
                ->icon('fas-code-branch')
                ->button()
                ->color('primary')
                ->tooltip('Cliquez pour changer l\'état du devis'),
            ActionGroup::make([
                // GenerateMsGraphEmailDraft::make('generateEmailDraft')
                //     ->templates([
                //         QuoteBasePdfTemplate::class,
                //     ]),
                GeneratePdfDownload::make('downloadPdf')
                    ->templates([
                        QuoteBasePdfTemplate::class,
                    ])
            ])->label('Produire')
                ->icon('fas-file-export')
                ->button()
                ->color('gray'),
            DeleteAction::make()->hidden(fn($record) => $record->state->isSaveHidden),
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
            IaUtils::PrismCorrectionAction(static::$resource, $this->record->state->isSaveHidden)->color('info'),
            $this->getCancelFormAction()
        ];
    }

    public function refreshInfolist()
    {
        $data = $this->getRecord()->fill($this->form->getState());
        $this->infolist->record($data);
        // Ne pas appeler ->render() sur un Schema dans Filament v4
    }

    /** Modifier les informations du document : l'action du bandeau du volet. */
    protected function getInfoEditAction(): Action
    {
        return Action::make('edit')
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
            ->slideOver();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->getRecord())
            ->components([
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


            ]);
    }
}