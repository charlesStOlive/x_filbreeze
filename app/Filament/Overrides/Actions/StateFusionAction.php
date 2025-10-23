<?php

namespace App\Filament\Overrides\Actions;

use A909M\FilamentStateFusion\Concerns\HasStateAttributes;
use A909M\FilamentStateFusion\Concerns\InteractsWithStateAction;
use App\Filament\Overrides\Concerns\ResolvesActionAttributes;
use A909M\FilamentStateFusion\Contracts\HasStateAttributesContract;
use A909M\FilamentStateFusion\Contracts\HasStateFusionAction;
use App\Filament\Contracts\HasRedirection;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class StateFusionAction extends Action implements HasStateAttributesContract, HasStateFusionAction
{
    use HasStateAttributes;
    use InteractsWithStateAction;
    use ResolvesActionAttributes; // Notre trait corrigé

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (Model $record) => $this->resolveLabel($record->{$this->getAttribute()}));
        $this->color(fn (Model $record) => $this->resolveColor($record->{$this->getAttribute()}));
        $this->icon(fn (Model $record) => $this->resolveIcon($record->{$this->getAttribute()}));
        $this->tooltip(fn (Model $record) => $this->resolveDescription($record->{$this->getAttribute()}));
        $this->setActionAttributes();

        $this->hidden(function (Model $record) {
            return ! $record?->{$this->getAttribute()}?->canTransitionTo($this->getToStateClass());
        });

        $this->action(function ($record, array $data) {
            // Vérifier la classe de transition AVANT d'exécuter la transition
            $transitionClass = $this->getTransitionClass();

            // Effectuer la transition
            if (empty($data)) {
                $record->{$this->getAttribute()}->transitionTo($this->getToStateClass());
            } else {
                $record->{$this->getAttribute()}->transitionTo($this->getToStateClass(), $data);
            }

            $this->success();

            // Maintenant vérifier s'il faut rediriger
            if ($transitionClass && class_exists($transitionClass)) {
                // Créer une instance de la transition avec les bonnes données
                $transition = empty($data) 
                    ? new $transitionClass($record)
                    : new $transitionClass($record, $data);
                    
                if ($transition instanceof HasRedirection) {
                    $redirectUrl = $transition->getRedirectUrl($record);
                    
                    if ($redirectUrl) {
                        return redirect($redirectUrl);
                    }
                }
            }
        });
        $this->after(fn ($record) => $record->refresh());
    }
}