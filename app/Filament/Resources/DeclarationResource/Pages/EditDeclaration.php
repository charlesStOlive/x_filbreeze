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
                ->visible(fn (): bool => $this->getRecord()->status === 'draft')
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
}
