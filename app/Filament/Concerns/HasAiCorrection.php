<?php

namespace App\Filament\Concerns;

use CharlesStOlive\FilamentPrism\Filament\Actions\CorrectionAction;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

/**
 * Correction orthographique (filament-prism) sur une page d'édition d'un
 * modèle `CorrectableWithItems` (devis, facture).
 *
 * `autoApply(false)` : la revue n'écrit pas elle-même dans le modèle — elle le
 * ferait sur une autre instance, en laissant le formulaire de la page afficher
 * l'ancien texte (qu'un « Enregistrer » réécrirait par-dessus). La page écrit
 * donc la correction sur son propre `record`, puis recharge le formulaire.
 */
trait HasAiCorrection
{
    protected function getAiCorrectionAction(): CorrectionAction
    {
        return CorrectionAction::make()
            ->correctable(fn () => $this->record)
            ->autoApply(false)
            ->hidden(fn (): bool => $this->record->state->isSaveHidden);
    }

    /**
     * Les valeurs sont relues depuis l'interaction en base (celle de ce
     * document), pas prises telles quelles depuis l'événement Livewire.
     *
     * @param  array<string, mixed>  $values
     */
    #[On('filament-prism:correction-applied')]
    public function applyAiCorrection(int $interactionId, array $values): void
    {
        $interaction = AiInteraction::find($interactionId);

        if (! $interaction?->correctable?->is($this->record) || $this->record->state->isSaveHidden) {
            return;
        }

        $this->record->applyValues(array_intersect_key($interaction->output ?? [], $values));
        $this->record->save();

        $this->fillForm();
        $this->unmountAction();

        Notification::make()->title('Correction appliquée')->success()->send();
    }
}
