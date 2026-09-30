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

class DraftToValidated extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    /** Clé de sa permission : `{liste}.transition.valider` (filament-permission-manager) ; seules les transitions faites par une personne en ont une. */
    public static string $key = 'valider';

    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private SupplierInvoice $supplierInvoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Passer de Draft à Validated');
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
        // Vérification 1: Le supplier_id OU invoice_number manquant → Warning
        if (empty($this->supplierInvoice->supplier_id) || empty($this->supplierInvoice->invoice_number)) {
            $this->supplierInvoice->state = new Warning($this->supplierInvoice);
            $this->supplierInvoice->save();

            $message = [];
            if (empty($this->supplierInvoice->supplier_id)) {
                $message[] = 'le fournisseur';
            }
            if (empty($this->supplierInvoice->invoice_number)) {
                $message[] = 'le numéro de facture';
            }
            $messageText = implode(' et ', $message);

            Notification::make()
                ->warning()
                ->title(__('Avertissement'))
                ->body(__("Il manque :message. Facture mise en Warning.", ['message' => $messageText]))
                ->send();

            return $this->supplierInvoice;
        }

        // Vérification 2: Le numéro de facture doit être unique pour ce supplier → Error
        $duplicateExists = SupplierInvoice::where('supplier_id', $this->supplierInvoice->supplier_id)
            ->where('invoice_number', $this->supplierInvoice->invoice_number)
            ->where('id', '!=', $this->supplierInvoice->id)
            ->exists();

        if ($duplicateExists) {
            $this->supplierInvoice->state = new Error($this->supplierInvoice);
            $this->supplierInvoice->save();

            Notification::make()
                ->danger()
                ->title(__('Erreur'))
                ->body(__('Le numéro de facture existe déjà pour ce fournisseur. Facture mise en Error.'))
                ->send();

            return $this->supplierInvoice;
        }

        // Si toutes les validations passent, on valide la facture
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
