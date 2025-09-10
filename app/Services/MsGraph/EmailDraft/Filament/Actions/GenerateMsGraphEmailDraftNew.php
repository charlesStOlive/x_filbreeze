<?php

namespace App\Services\MsGraph\EmailDraft\Filament\Actions;

use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Exception;
use Filament\Notifications\Notification;
use App\Dto\MsGraph\EmailMessageDTO;
use Illuminate\Support\Facades\Auth;
use App\Services\MsGraph\MsGraphEmailService;
use App\Services\MsGraph\EmailDraft\Base\EmailDraftRenderer;
use App\Services\Document\Filament\Actions\BaseDocumentAction;

class GenerateMsGraphEmailDraftNew extends BaseDocumentAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Générer brouillon Email')
            ->icon('fas-envelope-open-text');
    }

    protected function getServiceSchema($record): array
    {
        return [
            Flex::make([
                Group::make([
                    Select::make('template')
                        ->label('Modèle d\'email')
                        ->options(
                            collect($this->getTemplatesForRecord($record))
                                ->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
                        )
                        ->live()
                        ->required()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) use ($record) {
                            $templateClass = collect($this->getTemplatesForRecord($record))
                                ->first(fn($cls) => $cls::key() === $state);

                            if ($templateClass) {
                                $template = new $templateClass($record);
                                $options = $templateClass::getDefaultOptions();
                                $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                                $set('to', $template->getDefaultTo());
                                $set('subject', $rendered['subject']);
                                $set('template_options', $options);
                                $set('attachments', $templateClass::getDefaultAttachments());
                            }
                        }),

                    Select::make('to')
                        ->label('Destinataires')
                        ->multiple()
                        ->options(function (callable $get) use ($record) {
                            $key = $get('template');
                            if (!$key) return [];

                            $template = $this->getTemplateInstance($key, $record, $get('template_options') ?? []);
                            return $template?->getToOptions() ?? [];
                        })
                        ->required(),

                    TextInput::make('subject')
                        ->label('Sujet')
                        ->required(),

                    Group::make()
                        ->schema(function (callable $get) use ($record) {
                            $key = $get('template');
                            if (!$key) return [];

                            $template = $this->getTemplateInstance($key, $record);
                            return $template?->getForm() ?? [];
                        })
                        ->statePath('template_options')
                        ->columns(1),

                    Group::make()
                        ->schema(function (callable $get) use ($record) {
                            $key = $get('template');
                            $options = $get('template_options') ?? [];
                            
                            if (!$key) return [];

                            $template = $this->getTemplateInstance($key, $record, $options);
                            return $template && $template->hasPj()
                                ? [$template->getAttachmentForm()]
                                : [];
                        })

                ])->grow(false),

                Group::make([
                    ViewField::make('body')
                        ->label('Aperçu du contenu')
                        ->view('components.fields.email-preview')
                        ->viewData(function (callable $get) use ($record) {
                            $key = $get('template');
                            $options = $get('template_options') ?? [];

                            if (!$key) return ['body' => ''];

                            $template = $this->getTemplateInstance($key, $record, $options);
                            $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                            return [
                                'subject' => $rendered['subject'],
                                'body' => $rendered['body'],
                            ];
                        })
                        ->disabled(),
                ])->grow(),
            ])
        ];
    }

    protected function handleAction(array $data, $record): mixed
    {
        try {
            $msUser = Auth::user()?->msgUserDraft;

            if (!$msUser) {
                throw new Exception('Aucun utilisateur Microsoft Graph lié.');
            }

            $template = $this->getTemplateInstance(
                $data['template'],
                $record,
                $data['template_options'] ?? []
            );

            $rendered = app(EmailDraftRenderer::class)->render($template, $data['template_options'] ?? []);

            $attachments = $template->generateAttachments(
                $data['template_options'] ?? [],
                $data['attachments'] ?? []
            );

            $payload = [
                'subject' => $rendered['subject'],
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $rendered['body'],
                ],
                'toRecipients' => EmailMessageDTO::formatRecipientsFromEmails($data['to'] ?? []),
            ];

            app(MsGraphEmailService::class)->createNewDraftAndUploadAttachments($msUser, $payload, $attachments);

            Notification::make()
                ->title('Brouillon créé avec succès')
                ->success()
                ->send();
                
            return true;

        } catch (Exception $e) {
            Notification::make()
                ->title('Erreur lors de la création du brouillon')
                ->body($e->getMessage())
                ->danger()
                ->send();
                
            return false;
        }
    }
}
