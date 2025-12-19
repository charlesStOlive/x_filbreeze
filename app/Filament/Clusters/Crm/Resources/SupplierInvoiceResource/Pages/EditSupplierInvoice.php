<?php

namespace App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource\Pages;

use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use App\Filament\Utils\StateUtils;
use App\Models\States\SupplierInvoice\Draft;
use App\Models\States\SupplierInvoice\Validated;
use App\Models\States\SupplierInvoice\Error;
use App\Models\States\SupplierInvoice\Warning;
use App\Models\States\SupplierInvoice\SupplierInvoiceState;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Clusters\Crm\Resources\SupplierInvoiceResource;
use CharlesStOlive\FilamentStateFusionEnhanced\Actions\StateFusionAction;
use CharlesStOlive\FilamentStateFusionEnhanced\Actions\StateFusionActionGroup;

class EditSupplierInvoice extends EditRecord
{
    protected static string $resource = SupplierInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        \Log::info('EditSupplierInvoice - getHeaderActions called', [
            'record_is_null' => is_null($record),
            'record_class' => $record ? get_class($record) : 'null',
            'record_id' => $record?->id ?? 'null',
            'has_state' => $record && isset($record->state),
            'state_class' => $record && isset($record->state) ? get_class($record->state) : 'null',
            'is_draft' => $record && $record->state instanceof Draft,
        ]);

        return [
            StateUtils::getStateSaveButton(),

            // Si Draft → montrer les boutons spécifiques
            ActionGroup::make([
                StateFusionAction::make('state_validated')
                    ->transitionTo(Validated::class)
                    ->after(function ($record) {
                        return redirect()->to(SupplierInvoiceResource::getUrl('edit', ['record' => $record]));
                    }),

            ])
                ->label('Actions Draft')
                ->icon('fas-code-branch')
                ->button()
                ->color('primary')
                ->visible($record && $record->state instanceof Draft),

            // Pour les autres états → boutons automatiques
            StateFusionActionGroup::generate('state', SupplierInvoiceState::class)
                ->label('Changer état')
                ->icon('fas-code-branch')
                ->button()
                ->color('primary')
                ->tooltip('Cliquez pour changer l\'état')
                ->visible($record && !($record->state instanceof Draft)),

            Actions\DeleteAction::make(),
        ];
    }
}
