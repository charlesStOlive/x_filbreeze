<?php

namespace App\Models\States\SupplierInvoice;

use App\Models\SupplierInvoice;
use Closure;
use Filament\Forms;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;
use Filament\Notifications\Notification;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;

class ValidatedToDraft extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private SupplierInvoice $supplierInvoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Annuler la validation');
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public function handle(): SupplierInvoice
    {
        // Supprimer le fichier de SharePoint
        $this->supplierInvoice->deleteFromSharePoint();

        // Retour à l'état Draft
        $this->supplierInvoice->state = new Draft($this->supplierInvoice);
        $this->supplierInvoice->save();

        Notification::make()
            ->warning()
            ->title(__('Validation annulée'))
            ->body(__('La facture est retournée à l\'état Draft. Le fichier SharePoint a été supprimé.'))
            ->send();

        return $this->supplierInvoice;
    }

    public static function fill($model, $formData): self
    {
        return new self(
            supplierInvoice: $model,
            data: $formData,
        );
    }

    public function form(): array | Closure | null
    {
        return [];
    }
}
