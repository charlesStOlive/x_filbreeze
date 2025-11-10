<?php

namespace App\Services\Processors\Emails;

use Exception;
use App\Services\Ia\MistralAgentService;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailDraft;
use CharlesStOlive\MsGraphFilament\Support\PreflightResult;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailDraftProcessor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;

class DraftEmailProcessor extends BaseEmailDraftProcessor
{
    public static function getKey(): string
    {
        return 'd-cor';
    }
    public static function getIcon(): string
    {
        return 'heroicon-o-pencil-square';
    }
    public static function getLabel(): string
    {
        return 'Corriger le texte';
    }
    public static function getDescription(): string
    {
        return 'Lance une correction sur le texte';
    }
    public static function getDefaultTriggerCode(): string
    {
        return 'corrige';
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'agent_id' => 'ag:3e2c948d:20241122:correction-ortho-de-mails:2bf76447',
            'create_new_draft' => true,
            'regex_code' => 'corrige',
        ];
    }

    // --- UI pour ton builder ---
    public static function getForm(): array
    {
        return [
            TextInput::make('agent_id')
                ->label('ID Agent Mistral')
                ->helperText('Identifiant de l\'agent Mistral à utiliser pour la correction')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            Toggle::make('create_new_draft')
                ->label('Créer un nouveau brouillon')
                ->helperText('Si activé, crée un nouveau brouillon au lieu de modifier l\'original')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans l\'email pour déclencher la correction')
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

            TextEntry::make('create_new_draft')
                ->label('Créer un nouveau brouillon')
                ->helperText('Si activé, crée un nouveau brouillon au lieu de modifier l\'original')
                ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non'),

            TextEntry::make('regex_code')
                ->label('Code de déclenchement')
                ->formatStateUsing(fn($state) => "## {$state} ##")
                ->badge()
                ->color('primary')
                ->copyable()
                ->copyMessage('Code de déclenchement copié!')
                ->icon('heroicon-o-hashtag'),
        ];
    }

    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('corrected_content')
                ->label('Texte corrigé')
                ->html()
                ->limit(1000)
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('create_new_draft')
                ->label('Créer un nouveau brouillon')
                ->helperText('Si activé, crée un nouveau brouillon au lieu de modifier l\'original')
                ->formatStateUsing(fn($state) => $state ? 'Oui' : 'Non'),

            TextEntry::make('agent_used')
                ->label('Agent utilisé')
                ->copyable()
                ->copyMessage('Agent ID copié!')
                ->icon('heroicon-o-cpu-chip')
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
            $mode          = $this->getResult('mode', 'inactif');
            $codeOptions   = $this->getResult('code_options', []);
            $createNew     = (bool)$this->getServiceOption('create_new_draft', true);
            $agentId       = $this->getServiceOption('agent_id', static::getDefaults()['agent_id']);
            $updateExisting = ($codeOptions['u'] ?? false) || !$createNew;

            $clean = $this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyHtml);

            if ($mode === 'test') {
                $corrected = (new MistralAgentService())->callAgent($agentId, $clean);
                $this->finishProcessor(ProcessorStatus::Success, [
                    'corrected_content' => $corrected,
                    'processing_mode' => 'test',
                    'agent_used'      => $agentId,
                    'create_new_draft' => !$updateExisting,
                ], 'Correction testée avec succès');
                return $this->email;
            }

            $corrected = (new MistralAgentService())->callAgent($agentId, $clean);

            if ($updateExisting) {
                $this->updateBody($corrected);
                $data = [
                    'corrected_content' => $corrected,
                    'processing_mode'   => 'update',
                    'agent_used'        => $agentId,
                    'create_new_draft' => !$updateExisting,
                ];
                $message = 'Email corrigé et mis à jour';
            } else {
                $newData = $this->emailData->with(['bodyHtml' => $corrected]);
                $resp = $this->emailClient->createDraft($this->user, $newData);
                $this->markOriginalProcessed('Terminé - Brouillon corrigé créé');

                $data = [
                    'new_draft_id'      => $resp['id'] ?? null,
                    'corrected_content' => $corrected,
                    'processing_mode'   => 'new_draft',
                    'agent_used'        => $agentId,
                    'create_new_draft' => !$updateExisting,
                ];
                $message = 'Nouveau brouillon corrigé créé';
            }

            $this->finishProcessor(ProcessorStatus::Success, $data, $message);
        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, [], $e->getMessage());
        }

        return $this->email;
    }
}
