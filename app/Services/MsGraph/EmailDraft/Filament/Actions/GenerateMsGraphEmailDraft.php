<?php

namespace App\Services\MsGraph\EmailDraft\Filament\Actions;

use Exception;
use Filament\Actions\Action;
use App\Dto\MsGraph\EmailMessageDTO;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use App\Services\MsGraph\MsGraphEmailService;
use App\Services\MsGraph\EmailDraft\Base\EmailDraftRenderer;
use App\Services\Document\Filament\Actions\BaseDocumentAction;
use CharlesStOlive\MsGraphFilament\Infrastructure\MsGraph\GraphEmailService;
use CharlesStOlive\MsGraphFilament\Filament\Clusters\MsGraph\Resources\MsgDraftUserResource;

class GenerateMsGraphEmailDraft extends BaseDocumentAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Générer brouillon Email')
            ->icon('fas-envelope-open-text')
            ->fillForm(function ($record) {
                $templates = $this->getTemplatesForRecord($record);
                if (empty($templates)) return [];

                $templateClass = $templates[0]; // Premier template par défaut
                $template = new $templateClass($record);
                $options = $templateClass::getDefaultOptions();
                $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                return [
                    'template' => $templateClass::key(),
                    'to' => $template->getDefaultTo(),
                    'subject' => $rendered['subject'],
                    'template_options' => $options,
                    'attachments' => method_exists($templateClass, 'getDefaultAttachments') ? $templateClass::getDefaultAttachments() : [],
                ];
            });
    }

    protected function getServiceSchema($record = null): array
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
                            \Log::info('template class: ' . $templateClass);

                            if ($templateClass) {
                                $template = new $templateClass($record);
                                $options = $templateClass::getDefaultOptions();
                                $rendered = app(EmailDraftRenderer::class)->render($template, $options);
                                \Log::info(method_exists($templateClass, 'getDefaultAttachments') ? $templateClass::getDefaultAttachments() : []);
                                $set('to', $template->getDefaultTo());
                                $set('subject', $rendered['subject']);
                                $set('template_options', $options);
                                $set('attachments', method_exists($templateClass, 'getDefaultAttachments') ? $templateClass::getDefaultAttachments() : []);
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

                            if (!$key) return ['html' => ''];

                            $template = $this->getTemplateInstance($key, $record, $options);
                            $rendered = app(EmailDraftRenderer::class)->render($template, $options);

                            return [
                                'subject' => $rendered['subject'],
                                'html' => $rendered['body'],
                            ];
                        })
                        ->disabled(),
                ])->grow(),
            ])
        ];
    }

    protected function handleAction(array $data, $record = null): mixed
    {
        try {
            $msUser = Auth::user()?->msgUserDraft;

            if (!$msUser) {
                Notification::make()
                    ->title('Erreur Microsoft Graph')
                    ->body('L\'utilisateur n\'est pas connecté à Microsoft Graph')
                    ->danger()
                    ->actions([
                        Action::make('configure')
                            ->label('Configurer')
                            ->button()
                            ->url(MsgDraftUserResource::getUrl('index'))
                    ])
                    ->send();
                return false;
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
                'toRecipients' => collect($data['to'] ?? [])
                    ->map(fn($email) => ['emailAddress' => ['address' => $email]])
                    ->toArray(),
            ];

            app(GraphEmailService::class)->createNewDraftAndUploadAttachments($msUser, $payload, $attachments);

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

    protected function getExpectedTemplateType(): string
    {
        return \App\Services\MsGraph\EmailDraft\Base\BaseDraftEmailTemplate::class;
    }
}
