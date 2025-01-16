<?php

namespace App\Models\States\Invoice;

use Closure;
use DateTime;
use Filament\Forms;
use App\Models\Invoice;
use Spatie\ModelStates\Transition;
use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
use App\Filament\ModelStates\Concerns\ProvidesSpatieTransitionToFilament;

class ToSubmited extends Transition implements FilamentSpatieTransition
{
    use ProvidesSpatieTransitionToFilament;

    private Invoice $invoice;
    private DateTime $submited_at;

    public function __construct(Invoice $invoice, DateTime $submited_at = null)
    {
        $this->invoice = $invoice;
        $this->submited_at  = $submited_at ? $submited_at : now();
    }

    public function getLabel(): string
    {
        return __('Soumettre devis');
    }

    public function handle(): Invoice
    {
        $data = $this->invoice->toArray();

        // Valide les données avec les règles définies dans Submited
        // $validator = validator($data, Submited::rules());
        // if ($validator->fails()) {
        //     throw new ValidationException($validator);
        // }
        $this->invoice->state = new Submited($this->invoice);
        $this->invoice->submited_at = $this->submited_at;
        $this->invoice->save();
        return $this->invoice;
    }

    public static function fill($model, $formData): self
    {
        return new self(
            invoice: $model,
            submited_at: now(),
        );
    }

    public function form(): array | Closure | null
    {
        return [
            Forms\Components\DateTimePicker::make('submited_at')
                ->label('Validé le')
                ->default(now())
                ->helperText(__('Vous devez saisir une date de soumission.'))
        ];
    }
}
