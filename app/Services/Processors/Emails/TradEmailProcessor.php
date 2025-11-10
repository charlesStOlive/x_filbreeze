<?php

namespace App\Services\Processors\Emails;

use Exception;
use App\Services\Ia\MistralAgentService;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailDraft;
use CharlesStOlive\MsGraphFilament\Support\PreflightResult;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailDraftProcessor;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;

class TradEmailProcessor extends BaseEmailDraftProcessor
{
    public static function getKey(): string
    {
        return 'd-trad';
    }
    public static function getIcon(): string
    {
        return 'heroicon-o-language';
    }
    public static function getLabel(): string
    {
        return 'Traduire le mail';
    }
    public static function getDescription(): string
    {
        return 'Traduit le mail de la langue source vers la langue cible';
    }
    public static function getDefaultTriggerCode(): string
    {
        return 'traduit';
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'agent_id' => 'ag:3e2c948d:20241128:untitled-agent:863e968f',
            'create_new_draft' => true,
            'regex_code' => 'traduit',
        ];
    }

    // --- UI pour ton builder ---
    public static function getForm(): array
    {
        return [
            TextInput::make('agent_id')
                ->label('ID Agent Mistral')
                ->helperText('Identifiant de l\'agent Mistral à utiliser pour la traduction')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans l\'email pour déclencher la traduction')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        ];
    }

    public static function getInfoList(): array
    {
        return [
            TextEntry::make('agent_id')
                ->label('ID Agent Mistral')
                ->copyable()
                ->copyMessage('Agent ID copié!')
                ->icon('heroicon-o-cpu-chip'),

            TextEntry::make('regex_code')
                ->label('Code de déclenchement')
                ->formatStateUsing(fn($state) => "## {$state} ##")
                ->badge()
                ->color('gray')
                ->copyable()
                ->copyMessage('Code de déclenchement copié!')
                ->icon('heroicon-o-hashtag'),
        ];
    }

    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('target_language')
                ->label('Langue cible')
                ->badge()
                ->color('info')
                ->icon('heroicon-o-language')
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('translated_content')
                ->label('Contenu traduit')
                ->limit(1000)
                ->tooltip(fn($state) => $state)
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('processing_mode')
                ->label('Mode de traitement')
                ->badge()
                ->color(fn($state) => match ($state) {
                    'new_draft' => 'success',
                    'update'    => 'warning',
                    'test'      => 'info',
                    default     => 'gray'
                })
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('new_draft_id')
                ->label('ID nouveau brouillon')
                ->copyable()
                ->copyMessage('ID copié!')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn($state) => !empty($state)),
        ];
    }

    // --- Phase 1 ---
    public function preflight(): PreflightResult
    {
        if ($block = $this->guardAndCaptureCode()) {
            $this->updateProcessorStatus(ProcessorStatus::Blocked, $block->reason);
            $this->email->save();
            return $block;
        }

        try {
            $this->beginProcessor();
            $this->startProcessingWithPlaceholder();
        } catch (Exception $ex) {
            $this->updateProcessorStatus(ProcessorStatus::Error, $ex->getMessage());
            $this->email->has_error = true;
            $this->email->save();
            return PreflightResult::blocked('Préflight en échec');
        }

        return PreflightResult::ok();
    }

    // --- Phase 2 ---
    protected function perform(): MsgEmailDraft
    {
        try {
            $mode        = $this->getResult('mode', 'inactif');
            $codeOptions = $this->getResult('code_options', []);
            $lang        = array_key_first($codeOptions) ?: 'xx';
            $update      = (bool)($codeOptions['u'] ?? false);
            $agentId     = $this->getServiceOption('agent_id', static::getDefaults()['agent_id']);

            $clean  = $this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyHtml);
            $prompt = '[' . $lang . ']' . $clean;

            if ($mode === 'test') {
                $translated = (new MistralAgentService())->callAgent($agentId, $prompt);
                $this->finishProcessor(ProcessorStatus::Success, [
                    'target_language'   => $lang,
                    'translated_content' => $translated,
                    'processing_mode'   => 'test',
                ], "Traduction testée vers {$lang}");
                return $this->email;
            }

            $translated = (new MistralAgentService())->callAgent($agentId, $prompt);

            if ($update) {
                $this->updateBody($translated);
                $data = [
                    'target_language'    => $lang,
                    'translated_content' => $translated,
                    'processing_mode'    => 'update',
                ];
                $message = "Email traduit vers {$lang} et mis à jour";
            } else {
                $newEmailData = $this->emailData->with(['bodyHtml' => $translated]);
                $resp = $this->emailClient->createDraft($this->user, $newEmailData);
                $this->markOriginalProcessed('Terminé');

                $data = [
                    'new_draft_id'       => $resp['id'] ?? null,
                    'target_language'    => $lang,
                    'translated_content' => $translated,
                    'processing_mode'    => 'new_draft',
                ];
                $message = "Nouveau brouillon traduit vers {$lang} créé";
            }

            $this->finishProcessor(ProcessorStatus::Success, $data, $message);
        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, [], $e->getMessage());
        }

        return $this->email;
    }
}
