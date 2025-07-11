<?php

namespace App\Filament\Clusters\Crm\Resources\InvoiceResource\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Infolists;
use Filament\Forms\Form;
use Filament\Actions\Action;

use App\Filament\Utils\IaUtils;
use App\Filament\Utils\PdfUtils;
use Filament\Infolists\Infolist;
use App\Filament\Utils\StateUtils;
use App\Dto\MsGraph\EmailMessageDTO;

use App\Models\States\Invoice\Draft;
use App\Models\States\Invoice\Payed;
use Filament\Forms\Components\Group;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use App\Models\States\Invoice\Canceled;
use App\Models\States\Invoice\Submited;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;


use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\ModelStates\StateAction;
use Guava\FilamentClusters\Forms\Cluster;
use Filament\Infolists\Components\TextEntry;
use App\Services\MsGraph\MsGraphEmailService;
use App\Services\MsGraph\EmailDraft\EmailDraftRenderer;
use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use Pboivin\FilamentPeek\Pages\Concerns\HasPreviewModal;
use App\Services\MsGraph\EmailDraft\EmailDraftTemplateRegistry;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;
    use HasPreviewModal;


    protected $listeners = ['totalsUpdated' => 'refreshInfolist'];
    protected static string $view = 'filament.templates.form-info-list';

    protected function getHeaderActions(): array
    {
        return [
            StateUtils::getStateSaveButton(),
            InvoiceResource::getDuplicateAction(),
            StateAction::make('state_submited')
                ->transitionTo(Submited::class)
                ->after(function ($record) {
                    return redirect()->to(InvoiceResource::getUrl('edit', ['record' => $record]));
                }),
            StateAction::make('state_payed')
                ->transitionTo(Payed::class)
                ->after(function () {
                    return redirect()->to(InvoiceResource::getUrl('index'));
                }),
            StateAction::make('state_canceled')
                ->transitionTo(Canceled::class)
                ->after(function () {
                    return redirect()->to(InvoiceResource::getUrl('index'));
                }),
            Action::make('generateEmailDraft')
                ->label('Générer un email')
                ->form(fn($record) => [

                    Select::make('template')
                        ->label('Modèle d’email')
                        ->options(
                            collect(EmailDraftTemplateRegistry::getTemplatesFor('invoice'))
                                ->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
                        )
                        ->live()
                        ->required()
                        ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                            $templateClass = collect(EmailDraftTemplateRegistry::getTemplatesFor('invoice'))
                                ->first(fn($cls) => $cls::key() === $state);

                            if (! $templateClass) return;

                            $template = new $templateClass($record);
                            $options = $templateClass::getDefaultOptions();

                            $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                            $set('subject', $rendered['subject']);
                            $set('body', $rendered['body']);
                            $set('template_options', $options);
                        }),
                    Select::make('to')
                        ->label('Destinataires')
                        ->multiple()
                        ->options([
                            $record->contact->email => $record->contact->email,
                        ]),

                    // Options dynamiques spécifiques au template
                    Group::make()
                        ->schema(function (callable $get, $record) {
                            $key = $get('template');

                            $templateClass = collect(EmailDraftTemplateRegistry::getTemplatesFor('invoice'))
                                ->first(fn($cls) => $cls::key() === $key);

                            if (! $templateClass) return [];

                            return $templateClass::getForm($templateClass::getDefaultOptions());
                        })
                        ->statePath('template_options')
                        ->columns(1),

                    TextInput::make('subject')
                        ->label('Sujet')
                        ->required(),

                    ViewField::make('body')
                        ->label('Aperçu HTML')
                        ->view('components.fields.email-preview')
                        ->viewData(function (callable $get, $set, $record) {
                            $templateKey = $get('template');
                            $options = $get('template_options') ?? [];
                            //\Log::info('start',$options);

                            $templateClass = collect(EmailDraftTemplateRegistry::getTemplatesFor('invoice'))
                                ->first(fn($cls) => $cls::key() === $templateKey);

                            if (! $templateClass) return ['html' => '<p>Template introuvable</p>'];

                            

                            $template = new $templateClass($record);
                            //\Log::info('avant',$options);
                            $rendered  = app(EmailDraftRenderer::class)->render($template, $options);
                            $set('subject', $rendered['subject']);

                            // On peut aussi mettre à jour subject dynamiquement ici si tu veux
                            return ['html' => $rendered['body']];
                        })
                        ->disabled(),
                ])
                ->fillForm(function ($record) {
                    $templateClass = EmailDraftTemplateRegistry::getTemplatesFor('invoice')[0];
                    $options = $templateClass::getDefaultOptions();
                    $template = new $templateClass($record);
                    $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                    return [
                        'template' => $templateClass::key(),
                        'to' => [$record->contact->email],
                        'subject' => $rendered['subject'],
                        'body' => $rendered['body'],
                        'template_options' => $options,
                    ];
                })
                ->action(function (array $data, $record) {
                    $msUser = Auth::user()?->msgUserDraft;

                    if (! $msUser) {
                        throw new \Exception('Aucun utilisateur Microsoft Graph lié.');
                    }

                    $template = EmailDraftTemplateRegistry::getTemplateInstance($data['template'], $record);

                    $rendered = app(EmailDraftRenderer::class)->render($template, $data['template_options'] ?? []);

                    $dto = EmailMessageDTO::fromUserInput([
                        'subject' => $data['subject'],
                        'body' => $rendered['body'],
                        'to' => EmailMessageDTO::formatRecipientsFromEmails($data['to']),
                    ]);

                    //\Log::info($rendered['body']);

                    app(MsGraphEmailService::class)->createNewDraftFromScratch($msUser, $dto);

                    Notification::make()
                        ->title('Brouillon généré')
                        ->success()
                        ->send();
                })

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
                                'description' => $record->description,
                                'title' => $record->title,
                                'contact_id' => $record->contact_id,
                                'modalite' => $record->modalite,
                                'tx_tva' => $record->tx_tva,
                                'submited_at' => $record->submited_at,
                            ])
                            ->form([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        ...InvoiceResource::getContactAndCompanyFields(false),
                                        Forms\Components\TextInput::make('title')
                                            ->label('Titre')
                                            ->required()
                                            ->columnSpan(fn($record) => $record->state == 'draft' ? 1 : 2),
                                        Forms\Components\MarkdownEditor::make('description')
                                            ->label('Description de la facture')
                                            ->columnSpanFull(),
                                        Forms\Components\DatePicker::make('submited_at')
                                            ->label('Date de soumission')
                                            ->required()
                                            ->visible(fn($record) => $record->state == 'draft' ? false : true)
                                            ->dehydrated(fn($state) => filled($state)),
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
                            ])
                            ->action(function (array $data, $record): void {
                                //\Log::info('Editing invoice', ['data' => $data]);
                                $record->update($data);
                            })
                            ->slideOver(),
                    ])
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->label('Code'),
                                Infolists\Components\TextEntry::make('state')
                                    ->label('État')
                                    ->badge(),
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
            ])->columns(3);
    }
}
