<?php

namespace App\Models\States\SupplierInvoice;

use App\Models\SupplierInvoice;
use Closure;
use Filament\Forms;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;

class DraftToError extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private SupplierInvoice $supplierInvoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Passer de Draft à Error');
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-exclamation-circle';
    }

    public function handle(): SupplierInvoice
    {
        $this->supplierInvoice->state = new Error($this->supplierInvoice);
        $this->supplierInvoice->save();
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
        return [
            // Forms\Components\Textarea::make('error_message')
            //     ->label('Message d\'erreur')
            //     ->helperText(__('Détails de l\'erreur'))
        ];
    }
}
