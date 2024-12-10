<?php

namespace App\Filament\Clusters\Crm\Resources\QuoteResource\Pages;

use Filament\Forms;
use App\Models\Quote;
use Filament\Actions;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Filament\Resources\Pages\EditRecord;
use Pboivin\FilamentPeek\Pages\Actions\PreviewAction;
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
            Actions\Action::make('generate_quote')
                ->label('Génerer un devis')
                ->action(function ($record, $data) {
                    $pdf = \PDF::loadView('pdf.quote.main', ['quote' => $record])
                        ->setPaper('a4')
                        ->setOption('margin-top', '15mm')
                        ->setOption('margin-bottom', '15mm');
                    return $pdf->stream();
                }),
            PreviewAction::make()->label('Prévisualiser le devis'),
        ];
    }

    protected function getPreviewModalView(): ?string
    {
        // This corresponds to resources/views/posts/preview.blade.php
        return 'pdf.quote.main';
    }

    protected function getPreviewModalDataRecordKey(): ?string
    {
        return 'quote';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Split::make([
                    // Section principale
                    Forms\Components\Section::make('Edition')
                        ->schema([
                            Forms\Components\Fieldset::make('Base')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('Titre')
                                        ->required(),
                                    Forms\Components\DatePicker::make('end_at')
                                        ->label('Fin')
                                        ->default(now()->addMonth())
                                        ->required(),
                                ])
                                ->columns(2),
                            ...QuoteResource::getItemsBuilderComponent(),
                        ]),
                    // Section latérale
                    Forms\Components\Section::make('Informations')
                        ->schema([
                            Forms\Components\TextInput::make('code')
                                ->label('Code')
                                ->disabled(),
                            Forms\Components\TextInput::make('status')
                                ->label('État')
                                ->disabled(),
                            Forms\Components\TextInput::make('version')
                                ->label('Version')
                                ->disabled(),
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
                                    ->modalHeading(fn($record) => $record->total_ht == 0
                                        ? 'Validation impossible'
                                        : 'Valider ce devis')
                                    ->modalDescription(fn($record) => $record->total_ht == 0
                                        ? new HtmlString("Il est interdit de valider un devis dont le montant HT est égal à 0.")
                                        : new HtmlString("Attention <b>valider</b> un devis verrouille en partie ce devis<br> Avez-vous eu un BDC ou êtes-vous certain de vouloir la faire ?"))
                                    ->form(fn($record) => $record->total_ht == 0
                                        ? []
                                        : [
                                            Forms\Components\DateTimePicker::make('validated_at')
                                                ->label('Validé le')
                                                ->default(now())
                                        ])
                                    ->action(function ($record, $data) {
                                        if ($record->total_ht == 0) {
                                            return;
                                        }

                                        $record->validated_at = $data['validated_at'];
                                        $record->status = 'validated';
                                        $record->save();

                                        return redirect()->to(QuoteResource::getUrl('edit', ['record' => $record]));
                                    })
                                    ->modalSubmitAction(fn($record) => $record->total_ht == 0 ? false : true)
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
                                    ->action(function ($record) {
                                        $newRecord = $record->createNewVersion();
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

                            ...QuoteResource::getContactAndCompanyFields(false),
                            // Forms\Components\DateTimePicker::make('validated_at')
                            //     ->label('Validé le')
                            //     ->disabled(),
                            // Forms\Components\DateTimePicker::make('created_at')
                            //     ->label('Créé le')
                            //     ->disabled(),
                        ])
                        ->grow(false)
                        ->compact()
                        ->columns(1),
                ])->from('md')->columnSpanFull(),
            ]);
    }
}
