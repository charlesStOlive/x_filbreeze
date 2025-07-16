<?php

namespace App\Filament\Components\Actions;

use Filament\Actions\Action;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use App\Dto\MsGraph\EmailMessageDTO;
use App\Services\MsGraph\EmailDraft\EmailDraftRenderer;
use App\Services\MsGraph\EmailDraft\EmailDraftTemplateRegistry;
use App\Services\MsGraph\MsGraphEmailService;
use Filament\Notifications\Notification;

class GenerateMsGraphEmailDraft extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Générer brouillon Email')
            ->modalWidth('7xl')
            ->fillForm(function ($record) {
                $template = EmailDraftTemplateRegistry::getDefaultTemplateInstance($record);
                $templateClass = get_class($template);
                $options = $templateClass::getDefaultOptions();
                $rendered = app(EmailDraftRenderer::class)->render($template, $options);
                \Log::info($templateClass::getDefaultAttachments());

                return [
                    'template' => $templateClass::key(),
                    'to' => [$record->contact->email],
                    'subject' => $rendered['subject'],
                    'body' => $rendered['body'],
                    'template_options' => $options,
                    'attachments' => $templateClass::getDefaultAttachments(),
                ];
            })
            ->form(fn($record) => [
                Forms\Components\Split::make([
                    Forms\Components\Group::make([
                        Forms\Components\Select::make('template')
                            ->label('Modèle d’email')
                            ->options(
                                collect(EmailDraftTemplateRegistry::getTemplatesFor(
                                    EmailDraftTemplateRegistry::resolveModelTypeFromRecord($record)
                                ))->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
                            )
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                                $templateClass = collect(EmailDraftTemplateRegistry::getTemplatesFor(
                                    EmailDraftTemplateRegistry::resolveModelTypeFromRecord($record)
                                ))->first(fn($cls) => $cls::key() === $state);

                                if (! $templateClass) return;

                                $template = new $templateClass($record);
                                $options = $templateClass::getDefaultOptions();
                                $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                                $set('subject', $rendered['subject']);
                                $set('body', $rendered['body']);
                                $set('template_options', $options);
                            }),

                        Forms\Components\Select::make('to')
                            ->label('Destinataires')
                            ->multiple()
                            ->options(fn($record) => [
                                $record->contact->email => $record->contact->email,
                            ]),

                        Forms\Components\Group::make()
                            ->schema(function (callable $get, $record) {
                                $key = $get('template');
                                $templateClass = collect(EmailDraftTemplateRegistry::getTemplatesFor(
                                    EmailDraftTemplateRegistry::resolveModelTypeFromRecord($record)
                                ))->first(fn($cls) => $cls::key() === $key);

                                return $templateClass
                                    ? $templateClass::getForm($templateClass::getDefaultOptions())
                                    : [];
                            })
                            ->statePath('template_options')
                            ->columns(1),

                        Forms\Components\TextInput::make('subject')
                            ->label('Sujet')
                            ->required(),

                        Forms\Components\Group::make()
                            ->schema(function (callable $get, $record) {
                                $key = $get('template');
                                $templateClass = collect(EmailDraftTemplateRegistry::getTemplatesFor(
                                    EmailDraftTemplateRegistry::resolveModelTypeFromRecord($record)
                                ))->first(fn($cls) => $cls::key() === $key);

                                if (! $templateClass) return [];

                                $template = new $templateClass($record);

                                return $template->hasPj()
                                    ? [$template->getAttachmentForm()]
                                    : [];
                            })
                    ]),

                    Forms\Components\ViewField::make('body')
                        ->label('Aperçu HTML')
                        ->view('components.fields.email-preview')
                        ->viewData(function (callable $get, $record) {
                            $templateKey = $get('template');
                            $options = $get('template_options') ?? [];
                            $templateClass = collect(EmailDraftTemplateRegistry::getTemplatesFor(
                                EmailDraftTemplateRegistry::resolveModelTypeFromRecord($record)
                            ))->first(fn($cls) => $cls::key() === $templateKey);

                            if (! $templateClass) return ['html' => '<p>Template introuvable</p>'];

                            $template = new $templateClass($record);
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
                    throw new \Exception('Aucun utilisateur Microsoft Graph lié.');
                }

                $template = EmailDraftTemplateRegistry::getTemplateInstance($data['template'], $record);
                $rendered = app(EmailDraftRenderer::class)->render($template, $data['template_options'] ?? []);

                $dto = EmailMessageDTO::fromUserInput([
                    'subject' => $data['subject'],
                    'body' => $rendered['body'],
                    'to' => EmailMessageDTO::formatRecipientsFromEmails($data['to']),
                ]);

                app(MsGraphEmailService::class)->createNewDraftFromScratch($msUser, $dto);

                Notification::make()
                    ->title('Brouillon généré')
                    ->success()
                    ->send();
            });
    }
}
