<?php

namespace App\Models\States\SupplierInvoice;

use App\Models\SupplierInvoice;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;
use Filament\Notifications\Notification;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;

class ErrorToDraft extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private SupplierInvoice $supplierInvoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Repasser en Draft');
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-arrow-uturn-left';
    }

    public function handle(): SupplierInvoice
    {
        $this->supplierInvoice->state = new Draft($this->supplierInvoice);
        $this->supplierInvoice->save();

        Notification::make()
            ->info()
            ->title(__('Remise en Draft'))
            ->body(__('La facture a été remise en brouillon pour correction.'))
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
}
