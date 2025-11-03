<?php

namespace App\Filament\Components\Tables;

use Filament\Tables\Columns\Column;

class MailServiceColumn extends Column
{
    protected string $view = 'filament.tables.columns.mail-service-column';

    protected string $serviceType = 'email-draft';   // ex: 'email-draft', 'email-in'
    protected string $openMode = 'view';             // 'view' | 'edit' | 'results'
    protected string $modalWidth = 'xl';             // 'xs', 'sm', 'md', 'lg', 'xl', '2xl', etc.
    protected string $buttonSize = 'w-24 h-24';      // Classes Tailwind pour la taille des boutons
    protected bool $showMessage = false;             // Afficher le message de retour à côté de l'icône

    public function serviceType(string $type): static
    {
        $this->serviceType = $type;
        return $this;
    }

    public function openMode(string $mode = 'view'): static
    {
        $this->openMode = in_array($mode, ['view', 'edit', 'results'], true) ? $mode : 'view';
        return $this;
    }

    public function modalWidth(string $width): static
    {
        $this->modalWidth = $width;
        return $this;
    }

    public function buttonSize(string $size): static
    {
        $this->buttonSize = $size;
        return $this;
    }

    public function showMessage(bool $show = true): static
    {
        $this->showMessage = $show;
        return $this;
    }

    /** Données injectées dans la vue de la colonne (v4) */
    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'serviceType' => $this->serviceType,
            'openMode'    => $this->openMode,
            'modalWidth'  => $this->modalWidth,
            'buttonSize'  => $this->buttonSize,
            'showMessage' => $this->showMessage,
        ]);
    }

    /**
     * L'état retourné par la colonne contient les informations nécessaires
     * pour initialiser la cellule Livewire.
     */
    public function getState(): mixed
    {
        return [
            'record'      => $this->getRecord(), // Passer le record complet
            'serviceType' => $this->serviceType,
            'openMode'    => $this->openMode,
            'modalWidth'  => $this->modalWidth,
            'buttonSize'  => $this->buttonSize,
            'showMessage' => $this->showMessage,
        ];
    }
}
