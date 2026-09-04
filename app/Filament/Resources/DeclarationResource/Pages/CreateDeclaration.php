<?php

namespace App\Filament\Resources\DeclarationResource\Pages;

use App\Filament\Resources\DeclarationResource;
use App\Models\Declaration;
use App\Services\Declarations\DeclarationCalculator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDeclaration extends CreateRecord
{
    protected static string $resource = DeclarationResource::class;

    protected function afterFill(): void
    {
        $this->fillCalculationPreview(Declaration::TYPE_VAT);
    }

    private function fillCalculationPreview(string $type): void
    {
        $preview = app(DeclarationCalculator::class)->next($type);

        $this->data = array_merge($this->data ?? [], [
            'type' => $type,
            'calculation_mode' => Declaration::MODE_AUTOMATIC,
            'period_start' => $preview['period_start'],
            'period_end' => $preview['period_end'],
            'covered_months_display' => implode(', ', $preview['covered_months']),
            'client_invoice_count' => count($preview['calculation_details']['client_invoice_ids'] ?? []),
            'supplier_invoice_count' => count($preview['calculation_details']['qonto_supplier_invoice_ids'] ?? []),
            'turnover_excluding_tax' => $preview['turnover_excluding_tax_cents'] / 100,
            'vat_collected' => $preview['vat_collected_cents'] / 100,
            'vat_deductible' => $preview['vat_deductible_cents'] / 100,
            'vat_due' => $preview['vat_due_cents'] / 100,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshCalculation')
                ->label('Rafraîchir le calcul')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => ($this->data['calculation_mode'] ?? Declaration::MODE_AUTOMATIC) === Declaration::MODE_AUTOMATIC)
                ->action(function (): void {
                    $this->fillCalculationPreview($this->data['type'] ?? Declaration::TYPE_VAT);

                    Notification::make()
                        ->title('Calcul actualisé')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['calculation_mode'] ?? Declaration::MODE_AUTOMATIC) === Declaration::MODE_MANUAL) {
            $period = app(DeclarationCalculator::class)->next($data['type']);

            $manualAmounts = [
                'turnover_excluding_tax_cents' => $data['type'] === Declaration::TYPE_URSSAF
                    ? $this->toCents($data['turnover_excluding_tax'] ?? 0)
                    : 0,
                'vat_collected_cents' => $data['type'] === Declaration::TYPE_VAT
                    ? $this->toCents($data['vat_collected'] ?? 0)
                    : 0,
                'vat_deductible_cents' => $data['type'] === Declaration::TYPE_VAT
                    ? $this->toCents($data['vat_deductible'] ?? 0)
                    : 0,
                'vat_due_cents' => $data['type'] === Declaration::TYPE_VAT
                    ? $this->toCents($data['vat_due'] ?? 0)
                    : 0,
            ];

            unset($data['turnover_excluding_tax'], $data['vat_collected'], $data['vat_deductible'], $data['vat_due']);

            return array_merge($data, $manualAmounts, [
                'period_start' => $period['period_start'],
                'period_end' => $period['period_end'],
                'covered_months' => $period['covered_months'],
                'calculation_details' => [
                    'mode' => Declaration::MODE_MANUAL,
                    'entered_at' => now()->toIso8601String(),
                ],
                'created_by' => auth()->id(),
            ]);
        }

        $calculated = app(DeclarationCalculator::class)->next($data['type']);

        return array_merge($data, $calculated, [
            'calculation_mode' => Declaration::MODE_AUTOMATIC,
            'created_by' => auth()->id(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('edit', ['record' => $this->record]);
    }

    private function toCents(mixed $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }
}
