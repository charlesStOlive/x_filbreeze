<?php

namespace App\Services\MsGraph\EmailDraft\Filament\Actions;

use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Exception;
use Filament\Forms;
use Filament\Actions\Action;
use App\Dto\MsGraph\EmailMessageDTO;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use App\Services\MsGraph\MsGraphEmailService;
use App\Services\MsGraph\EmailDraft\Base\EmailDraftRenderer;
use App\Services\MsGraph\EmailDraft\Base\EmailDraftTemplateRegistry;

class GenerateMsGraphEmailDraft extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Générer brouillon Email')
            ->icon('fas-envelope-open-text')
            ->modalWidth('7xl')
            ->fillForm(function ($record) {
                $template = EmailDraftTemplateRegistry::getDefaultTemplateInstance($record);
                $templateClass = get_class($template);
                $options = $templateClass::getDefaultOptions();
                $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                return [
                    'template' => $templateClass::key(),
                    'to' => $template->getDefaultTo(),
                    'subject' => $rendered['subject'],
                    'body' => $rendered['body'],
                    'template_options' => $options,
                    'attachments' => $templateClass::getDefaultAttachments(),
                ];
            })
            ->schema(fn ($record) => [
                Flex::make([
                    Group::make([
                        Select::make('template')
                            ->label('Modèle d’email')
                            ->options(
                                collect(EmailDraftTemplateRegistry::getTemplatesFor(
                                    EmailDraftTemplateRegistry::resolveModelTypeFromRecord($record)
                                ))->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
                            )
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) use ($record) {
                                $template = EmailDraftTemplateRegistry::getTemplateInstance($state, $record);

                                if (! $template) return;

                                $options = $template::getDefaultOptions();
                                $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                                $set('template_options', $options);
                                $set('subject', $rendered['subject']);
                                $set('body', $rendered['body']);
                                $set('attachments', $template::getDefaultAttachments());
                                $set('to', $template->getDefaultTo());
                            }),

                        Select::make('to')
                            ->label('Destinataires')
                            ->multiple()
                            ->options(function ($get, $record) {
                                $template = EmailDraftTemplateRegistry::getTemplateInstance(
                                    $get('template'),
                                    $record,
                                    $get('template_options') ?? []
                                );
                                return $template?->getToOptions() ?? [];
                            }),

                        Group::make()
                            ->schema(function (callable $get, $record) {
                                $key = $get('template');
                                $options = $get('template_options') ?? [];
                                $template = EmailDraftTemplateRegistry::getTemplateInstance($key, $record, $options);
                                return $template?->getForm() ?? [];
                            })
                            ->statePath('template_options')
                            ->columns(1),

                        TextInput::make('subject')
                            ->label('Sujet')
                            ->required(),

                        Group::make()
                            ->schema(function (callable $get, $record) {
                                $key = $get('template');
                                $options = $get('template_options') ?? [];
                                $template = EmailDraftTemplateRegistry::getTemplateInstance($key, $record, $options);
                                return $template && $template->hasPj()
                                    ? [$template->getAttachmentForm()]
                                    : [];
                            }),
                    ]),

                    ViewField::make('body')
                        ->label('Aperçu HTML')
                        ->view('components.fields.email-preview')
                        ->viewData(function (callable $get, $record) {
                            $templateKey = $get('template');
                            $options = $get('template_options') ?? [];
                            $template = EmailDraftTemplateRegistry::getTemplateInstance($templateKey, $record, $options);
                            if (! $template) return ['html' => '<p>Template introuvable</p>'];

                            $rendered = app(EmailDraftRenderer::class)->render($template, $options);
                            return ['html' => $rendered['body']];
                        })
                        ->disabled()
                        ->grow(false),
                ])
            ])
            ->action(function (array $data, $record) {
                $msUser = Auth::user()?->msgUserDraft;

                if (! $msUser) {
                    throw new Exception('Aucun utilisateur Microsoft Graph lié.');
                }

                $template = EmailDraftTemplateRegistry::getTemplateInstance(
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
                    ->title('Brouillon généré')
                    ->success()
                    ->send();
            });
    }
}