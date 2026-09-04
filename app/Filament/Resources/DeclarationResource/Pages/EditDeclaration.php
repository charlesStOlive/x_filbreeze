<?php

namespace App\Filament\Resources\DeclarationResource\Pages;

use App\Filament\Resources\DeclarationResource;
use App\Models\Declaration;
use App\Services\Declarations\DeclarationCalculator;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDeclaration extends EditRecord
{
    protected static string $resource = DeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshCalculation')
                ->label('Rafraîchir le calcul')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->getRecord()->status === 'draft' && $this->getRecord()->calculation_mode === Declaration::MODE_AUTOMATIC)
                ->action(function (): void {
                    /** @var Declaration $declaration */
                    $declaration = $this->getRecord();
                    $calculated = app(DeclarationCalculator::class)->calculate(
                        $declaration->type,
                        $declaration->period_start,
                    );

                    $declaration->forceFill($calculated)->save();
                    $this->fillForm();

                    Notification::make()
                        ->title('Calcul actualisé')
                        ->success()
                        ->send();
                }),
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->status === 'draft'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Declaration $declaration */
        $declaration = $this->getRecord();

        return array_merge($data, [
            'turnover_excluding_tax' => $declaration->turnover_excluding_tax,
            'previous_vat_credit' => $declaration->previous_vat_credit,
            'vat_collected' => $declaration->vat_collected,
            'vat_deductible' => $declaration->vat_deductible,
            'vat_due' => $declaration->vat_due,
            'vat_credit' => $declaration->vat_credit,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord()->calculation_mode !== Declaration::MODE_MANUAL) {
            return $data;
        }

        $vatCollectedCents = $this->toCents($data['vat_collected'] ?? $this->getRecord()->vat_collected);
        $vatDeductibleCents = $this->toCents($data['vat_deductible'] ?? $this->getRecord()->vat_deductible);
        $calculator = app(DeclarationCalculator::class);
        $previousVatCreditCents = $calculator->previousVatCreditCents(
            $this->getRecord()->period_start,
            (int) $this->getRecord()->getKey(),
        );
        $vatBalance = $calculator
            ->vatBalanceCents($vatCollectedCents, $vatDeductibleCents, $previousVatCreditCents);

        $amountFields = [
            'turnover_excluding_tax' => 'turnover_excluding_tax_cents',
            'vat_collected' => 'vat_collected_cents',
            'vat_deductible' => 'vat_deductible_cents',
        ];

        foreach ($amountFields as $formField => $databaseField) {
            $data[$databaseField] = $this->toCents($data[$formField] ?? $this->getRecord()->{$formField});
            unset($data[$formField]);
        }

        $data['vat_due_cents'] = $vatBalance['due'];
        unset($data['vat_due'], $data['vat_credit']);

        $data['calculation_details'] = array_merge(
            $this->getRecord()->calculation_details ?? [],
            [
                'mode' => Declaration::MODE_MANUAL,
                'updated_at' => now()->toIso8601String(),
            ],
        );

        return $data;
    }

    private function toCents(mixed $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }
}
