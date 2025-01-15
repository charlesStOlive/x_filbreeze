<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Forms;
use App\Models\Quote;
use Filament\Actions;
use Filament\Forms\Form;
use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;
use App\Filament\Utils\StateUtils;
use App\Models\States\Quote\Draft;
use Illuminate\Support\HtmlString;
use Spatie\Browsershot\Browsershot;
use App\Services\Helpers\ViteHelper;
use Illuminate\Support\Facades\View;
use App\Models\States\Quote\Validated;
use App\Models\States\Invoice\Submited;
use Filament\Resources\Pages\EditRecord;
use App\Filament\ModelStates\StateAction;
use Guava\FilamentClusters\Forms\Cluster;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;
    use HasPreviewModal;

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
                Forms\Components\Section::make([
                    // Section principale
                    Forms\Components\Section::make('Edition')
                        ->schema([
                            Forms\Components\Section::make('Base (titre, description, date de validité)')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('Titre')
                                        ->required(),
                                    Forms\Components\DatePicker::make('end_at')
                                        ->label('Fin')
                                        ->default(now()->addMonth())
                                        ->required(),
                                    ...QuoteResource::getContactAndCompanyFields(false),
                                    Forms\Components\MarkdownEditor::make('description')
                                        ->label('Description du devis')
                                        ->columnSpanFull(),
                                ])->collapsed(true)
                                ->columns(2),
                            ...QuoteResource::getItemsBuilderComponent(),
                        ])->compact()
                        ->columnSpan(3),
                    // Section latérale
                    Forms\Components\Section::make('Informations')
                        ->schema([
                            Cluster::make([
                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->disabled(),
                                Forms\Components\TextInput::make('state')
                                    ->label('État')
                                    ->disabled(),
                                Forms\Components\TextInput::make('version')
                                    ->label('Version')
                                    ->disabled(),
                            ])->label('Code / Etat / Version'),

                            Forms\Components\TextInput::make('total_ht_br')
                                ->label('Total avant remise HT')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(),
                            Forms\Components\TextInput::make('total_ht')
                                ->label('Total HT')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('activate_v')
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
                                Forms\Components\Actions\Action::make('deactivate')
                                    ->label('DéActiver')
                                    ->hidden(fn($record) => $record->state !== 'validated')
                                    ->action(function ($record) {
                                        $record->validated_at = null;
                                        $record->state = 'draft';
                                        $record->save();
                                        return redirect()->to(QuoteResource::getUrl('edit', ['record' => $record]));
                                    })
                                    ->color('danger'),
                            ])->fullWidth(),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('create_v')
                                    ->label('Créer une nouvelle version')
                                    ->action(function ($record, $component) {
                                        $data = $component->getState();
                                        $newRecord = $record->createNewVersion($data);
                                        return redirect()->to(QuoteResource::getUrl('edit', ['record' => $newRecord]));
                                    }),
                            ])
                            ->hidden(fn($record) => $record->hasOneVersionValidated())
                            ->fullWidth(),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('clean')
                                    ->label('Nettoyer autres V')
                                    ->action(function ($record) {
                                        $record->cleanUnactive();
                                    })
                                    ->disabled(fn($record) => !$record->is_retained || !($record->cleanUnactiveTest() > 0)),
                            ])->fullWidth(),
                        ])
                        ->compact()
                        ->columns(1)
                        ->columnSpan(1),
                ])->columns(4),
            ]);
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
}
