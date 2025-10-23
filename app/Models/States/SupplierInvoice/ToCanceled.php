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

class ToCanceled extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private SupplierInvoice $supplierInvoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Passer à canceled');
    }
 
    public function getColor(): string
    {
        return 'primary';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-arrow-right';
    }

     public function handle(): SupplierInvoice
    {
        $this->supplierInvoice->state = new Canceled($this->supplierInvoice);
        // Exemple: $this->supplierInvoice->validated_at = $this->data['validated_at'] ?? now();
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
            // Forms\Components\DateTimePicker::make('validated_at')
            //     ->label('Validé le')
            //     ->default(now())
            //     ->helperText(__('Date de validation'))
        ];
    }

}