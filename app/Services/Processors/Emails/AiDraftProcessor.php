<?php

namespace App\Services\Processors\Emails;

use Exception;
use RuntimeException;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailDraft;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailDraftProcessor;
use CharlesStOlive\MsGraphFilament\Support\DraftBody;
use Filament\Infolists\Components\TextEntry;

/**
 * Un processeur de brouillon qui fait retravailler le message par l'IA (traduction,
 * correction...) : tout est automatique, sans relecture.
 *
 * Seul le message rédigé est envoyé à l'IA : l'historique de la conversation cité en dessous
 * et l'enveloppe HTML du document sont remis tels quels autour du résultat (`DraftBody`).
 * Le résultat remplace le message du brouillon, ou part dans un nouveau brouillon avec
 * l'option `-n` (## code -n ##) ; en mode test, rien n'est écrit dans Outlook.
 *
 * Une sous-classe ne décrit que l'appel (`runAi()`, via une ressource IA de filament-prism)
 * et où lire le HTML obtenu (`outputHtml()`).
 */
abstract class AiDraftProcessor extends BaseEmailDraftProcessor
{
    /** L'option du code qui demande un nouveau brouillon au lieu de modifier celui-ci. */
    protected const NEW_DRAFT_OPTION = 'n';

    /**
     * L'appel à l'IA sur le message rédigé, rattaché au brouillon (`attachTo: $this->email`)
     * pour qu'un job relancé avec le même contenu réutilise la réponse déjà payée
     */
    abstract protected function runAi(string $message): AiInteraction;

    /** Le HTML produit par l'IA, lu dans la réponse résolue de l'interaction */
    abstract protected function outputHtml(AiInteraction $interaction): string;

    /** Le message de fin, selon le mode de traitement (test, update, new_draft) */
    abstract protected function successMessage(string $processingMode): string;

    /**
     * Phase 2: Action durant la queue
     * L'IA retravaille le message, puis le brouillon est mis à jour
     */
    protected function perform(): MsgEmailDraft
    {
        try {
            $body = DraftBody::fromHtml($this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyHtml));

            if ($body->messageIsEmpty()) {
                throw new RuntimeException('Rien à traiter : le brouillon ne contient pas de message au-dessus de l\'historique');
            }

            $interaction = $this->runAi($body->message);
            $html = $this->outputHtml($interaction);

            if ($html === '') {
                throw new RuntimeException('L\'IA a renvoyé un message vide');
            }

            // ✅ Stocker les résultats AVANT finishProcessor
            $this->setResult('content', $html);
            $this->setResult('tokens', $interaction->total_tokens);

            if ($this->isTestMode()) {
                // Rien n'est écrit dans Outlook : le résultat n'est pas appliqué
                $interaction->markDiscarded();
                $mode = 'test';
            } else {
                if ($this->wantsNewDraft()) {
                    $resp = $this->emailClient->createDraft($this->user, $this->emailData->with(['bodyHtml' => $body->withMessage($html)]));
                    $this->setResult('new_draft_id', $resp['id'] ?? null);
                    $mode = 'new_draft';
                } else {
                    $this->updateBody($body->withMessage($html));
                    $mode = 'update';
                }

                $this->markDraftAsCompleted();
                $interaction->markApplied();
            }

            $this->setResult('processing_mode', $mode);
            $this->finishProcessor(ProcessorStatus::Success, $this->successMessage($mode));
        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, $e->getMessage());
        }

        return $this->email;
    }

    /** Les options du code, sans -n (## traduit -en -n ## → ['en']) */
    protected function codeOptions(): array
    {
        return array_values(array_filter(
            array_map('strval', array_keys($this->getResult('code_options', []) ?? [])),
            fn(string $option) => $option !== self::NEW_DRAFT_OPTION,
        ));
    }

    protected function wantsNewDraft(): bool
    {
        return (bool) ($this->getResult('code_options', [])[self::NEW_DRAFT_OPTION] ?? false);
    }

    /** Les résultats communs, à compléter par ceux propres au processeur */
    protected static function commonResultsInfoList(string $contentLabel): array
    {
        return [
            TextEntry::make('content')
                ->label($contentLabel)
                ->html()
                ->limit(1000)
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

            TextEntry::make('tokens')
                ->label('Tokens consommés')
                ->icon('heroicon-o-cpu-chip')
                ->visible(fn($state) => !empty($state)),
        ];
    }
}
