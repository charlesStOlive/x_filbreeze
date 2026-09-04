<?php

namespace App\Models\States\Invoice;

use Filament\Forms\Components\DateTimePicker;
use Closure;
use DateTime;
use Filament\Forms;
use App\Models\Invoice;
use App\Services\Qonto\CrmInvoiceQontoService;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Throwable;
use Spatie\ModelStates\Transition;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
// use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
// use App\Filament\ModelStates\Concerns\ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Concerns\StateFusionInfo as ProvidesSpatieTransitionToFilament;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion as FilamentSpatieTransition;
use Filament\Support\Contracts\HasIcon;

class ToSubmited extends Transition implements FilamentSpatieTransition ,HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private Invoice $invoice,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Soumettre');
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-paper-airplane';
    }


    public function handle(): Invoice
    {
        try {
            $this->invoice->submited_at = $this->data['submited_at'] ?? now();

            $this->invoice = app(CrmInvoiceQontoService::class)->submit($this->invoice);

            $this->invoice->state = new Submited($this->invoice);
            $this->invoice->save();

            return $this->invoice;
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: $exception->getMessage();

            \Log::warning('Invoice submission validation failed', [
                'invoice_id' => $this->invoice->id,
                'message' => $message,
            ]);

            Notification::make()
                ->title('Soumission impossible')
                ->body($message)
                ->danger()
                ->persistent()
                ->send();

            throw new Halt();
        } catch (Throwable $exception) {
            report($exception);

            \Log::error('Invoice submission failed', [
                'invoice_id' => $this->invoice->id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title('Erreur pendant la soumission Qonto')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt();
        }
    }

    public static function fill($model, $formData): self
    {
        return new self(
            invoice: $model,
            data: $formData,
        );
    }

    public function form(): array | Closure | null
    {
        return [
            DateTimePicker::make('submited_at')
                ->label('Soumis le')
                ->default(now())
                ->required()
                ->helperText(__('Date utilisée comme date d’émission de la facture Qonto.'))
        ];
    }
}
