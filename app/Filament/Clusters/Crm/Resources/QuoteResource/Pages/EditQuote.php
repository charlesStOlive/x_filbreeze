<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Forms;
use App\Models\Quote;
use Filament\Actions;
use Filament\Forms\Form;
use App\Filament\Utils\PdfUtils;
use Illuminate\Support\HtmlString;
use Spatie\Browsershot\Browsershot;
use App\Services\Helpers\ViteHelper;
use Illuminate\Support\Facades\View;
use Filament\Resources\Pages\EditRecord;
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
            Actions\DeleteAction::make(),
            Actions\Action::make('duplicate')
                ->label('Dupliquer le devis')
                ->modalHeading('Dupliquer le devis')
                ->modalDescription(new HtmlString("Attention cette action permet de <b>dupliquer</b> un devis <br> pour créer une nouvelle version cliquez sur nouvelle vesion dans la page d'édition "))
                ->fillForm(fn($record): array => [
                    'client_id' => $record->client_id,
                    'contact_id' => $record->contact_id,
                ])
                ->form([
                    ...QuoteResource::getContactAndCompanyFields(),
                    Forms\Components\TextInput::make('title')
                        ->label('Titre')
                        ->required(),
                    Forms\Components\DatePicker::make('end_at')
                        ->label('Fin')
                        ->default(now()->addMonth())
                        ->required()
                ])
                ->action(function ($record, $data) {
                    $newRecord = $record->createNewReplication($data);
                    return redirect()->to(QuoteResource::getUrl('edit', ['record' => $newRecord]));
                }),
            $this->getSaveFormAction(),
            PdfUtils::CreateActionPdf('devis', 'pdf.quote.main')->icon('heroicon-o-document'),
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
                                Forms\Components\TextInput::make('status')
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
                                    ->hidden(fn($record) => $record->is_retained)
                                    ->action(function ($record) {
                                        $record->swapRetainedQuote();
                                    })
                                    ->color('success'),
                                Forms\Components\Actions\Action::make('validate')
                                    ->label('Valider ce devis')
                                    ->modalDescription(new HtmlString("Attention <b>valider</b> un devis verrouille en partie ce devis<br> Avez-vous eu un BDC ou êtes-vous certain de vouloir la faire ?"))
                                    ->form(fn($record) => $record->total_ht == 0
                                        ? []
                                        : [
                                            Forms\Components\DateTimePicker::make('validated_at')
                                                ->label('Validé le')
                                                ->default(now())
                                        ])
                                    ->action(function ($record, $data, $component) {
                                        $formData = $component->getState();
                                        $record->fill($formData);
                                        if ($record->total_ht == 0) {
                                            return;
                                        }
                                        $record->validated_at = $data['validated_at'];
                                        $record->status = 'validated';
                                        $record->save();
                                        return redirect()->to(QuoteResource::getUrl('edit', ['record' => $record]));
                                    })
                                    ->hidden(fn($record) => !$record->is_retained || $record->status === 'validated')
                                    ->color(fn($record) => $record->total_ht == 0 ? 'danger' : 'success'),
                                Forms\Components\Actions\Action::make('deactivate')
                                    ->label('DéActiver')
                                    ->hidden(fn($record) => $record->status !== 'validated')
                                    ->action(function ($record) {
                                        $record->validated_at = null;
                                        $record->status = 'draft';
                                        $record->save();
                                        return redirect()->to(QuoteResource::getUrl('edit', ['record' => $record]));
                                    })
                                    ->color('danger'),
                            ])->fullWidth(),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('create_v')
                                    ->label('Créer une nouvelle version')
                                    ->action(function ($record, $component)  {
                                        $data = $component->getState();
                                        $newRecord = $record->createNewVersion($data);
                                        return redirect()->to(QuoteResource::getUrl('edit', ['record' => $newRecord]));
                                    }),
                            ])->hidden(fn($record) => $record->status === 'validated')->fullWidth(),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('clean')
                                    ->label('Nettoyer autres V')
                                    ->action(function ($record) {
                                        $newRecord = $record->cleanUnactive();
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
            $this->getSaveFormAction(),
            PdfUtils::CreateActionPdf('devis', 'pdf.quote.main')->icon('heroicon-o-document'),
            $this->getCancelFormAction()
        ];
    }
}
