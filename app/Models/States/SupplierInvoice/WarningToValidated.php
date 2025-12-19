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
use Illuminate\Validation\ValidationException;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;

class WarningToValidated extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private SupplierInvoice $supplierInvoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Passer de Warning à Validated');
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-check-circle';
    }

    public function handle(): SupplierInvoice
    {
        // Bloquer si supplier_id est vide
        if (empty($this->supplierInvoice->supplier_id)) {
            Notification::make()
                ->danger()
                ->title(__('Erreur'))
                ->body(__('Le fournisseur est obligatoire pour valider une facture.'))
                ->send();

            throw ValidationException::withMessages([
                'supplier_id' => __('Le fournisseur est obligatoire pour valider une facture.')
            ]);
        }

        // Si pas de numéro de facture, ajouter une note
        if (empty($this->supplierInvoice->invoice_number)) {
            $date = now()->format('d/m/Y H:i');
            $user = auth()->user() ? auth()->user()->name : 'Utilisateur inconnu';
            $note = "Cette facture a été validée sans numéro le {$date} par {$user}.";

            $currentNotes = $this->supplierInvoice->notes;
            if (!empty($currentNotes)) {
                $this->supplierInvoice->notes = $currentNotes . "\n\n" . $note;
            } else {
                $this->supplierInvoice->notes = $note;
            }
        }

        $this->supplierInvoice->state = new Validated($this->supplierInvoice);
        $this->supplierInvoice->save();

        // Upload vers SharePoint uniquement en cas de succès
        $this->supplierInvoice->uploadToSharePoint();

        Notification::make()
            ->success()
            ->title(__('Succès'))
            ->body(__('La facture a été validée avec succès.'))
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
        return [
            // Forms\Components\DateTimePicker::make('validated_at')
            //     ->label('Validé le')
            //     ->default(now())
            //     ->helperText(__('Date de validation'))
        ];
    }
}
