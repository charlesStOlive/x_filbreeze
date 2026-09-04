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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord()->calculation_mode !== Declaration::MODE_MANUAL) {
            return $data;
        }

        $amountFields = [
            'turnover_excluding_tax' => 'turnover_excluding_tax_cents',
            'vat_collected' => 'vat_collected_cents',
            'vat_deductible' => 'vat_deductible_cents',
            'vat_due' => 'vat_due_cents',
        ];

        foreach ($amountFields as $formField => $databaseField) {
            $data[$databaseField] = $this->toCents($data[$formField] ?? $this->getRecord()->{$formField});
            unset($data[$formField]);
        }

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
