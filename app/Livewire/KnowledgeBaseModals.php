<?php

namespace App\Livewire;

use Guava\FilamentKnowledgeBase\Livewire\Modals;

/**
 * Le composant « Modals » de la base de connaissances, qui garde la page affichée quand on ferme la fenêtre.
 *
 * N'existe qu'à cause de la vue surchargée `filament-knowledge-base/livewire/modals` (qui décrit le bug du plugin qu'elle
 * contourne) : la fenêtre y est toujours présente, au lieu de disparaître avec sa page. Or celui de Guava remet la page à
 * zéro dès la fermeture (`close-modal`) : le texte partait environ 130 ms avant la fin de l'animation, et la fenêtre se
 * vidait sous les yeux — un volet blanc, avec son seul bouton « Fermer ». Ici, la page reste : la fenêtre se referme sur son
 * texte, et la page suivante la remplace à l'ouverture (`showDocumentation()`).
 *
 * La méthode surchargée n'a plus l'attribut `#[On('close-modal')]` : Livewire ne l'écoute plus. À retirer avec la vue.
 */
class KnowledgeBaseModals extends Modals
{
    public function onClose($id): void {}
}
