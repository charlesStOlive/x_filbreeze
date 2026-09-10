<?php

namespace App\Filament\Resources\DeclarationResource\Pages;

use App\Filament\Clusters\Crm\Resources\InvoiceResource;
use App\Filament\Resources\DeclarationResource;
use App\Models\Declaration;
use App\Models\Invoice;
use App\Services\Declarations\DeclarationCalculator;
use CharlesStOlive\FilamentQonto\Models\QontoSupplierInvoice;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\HtmlString;

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

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->getRecord())
            ->components([
                Section::make('Déclaration')
                    ->description('Fixés à la création, non modifiables.')
                    ->schema([
                        TextEntry::make('type')
                            ->label('Type')
                            ->formatStateUsing(fn (string $state): string => Declaration::typeOptions()[$state] ?? $state)
                            ->badge(),
                        TextEntry::make('frequency_label')->label('Fréquence'),
                        TextEntry::make('calculation_mode')
                            ->label('Mode de calcul')
                            ->formatStateUsing(fn (string $state): string => Declaration::modeOptions()[$state] ?? $state),
                        TextEntry::make('period_start')->label('Du')->date('d/m/Y'),
                        TextEntry::make('period_end')->label('Au')->date('d/m/Y'),
                        TextEntry::make('covered_months')
                            ->label('Mois couverts')
                            ->state(fn (Declaration $record): string => implode(', ', $record->covered_months ?? [])),
                    ])
                    ->columns(3),

                Section::make('URSSAF')
                    ->collapsible()
                    ->visible(fn (Declaration $record): bool => $record->type === Declaration::TYPE_URSSAF)
                    ->schema([
                        RepeatableEntry::make('urssaf_client_invoices')
                            ->hiddenLabel()
                            ->state(fn (Declaration $record): array => $this->clientInvoices($record))
                            ->schema([
                                TextEntry::make('payed_at')->label('Payée')->size(TextSize::ExtraSmall),
                                TextEntry::make('code')->label('N°')->html()->wrap()->size(TextSize::ExtraSmall)->columnSpan(2),
                                TextEntry::make('client_slug')->label('Clt')->html()->size(TextSize::ExtraSmall),
                                TextEntry::make('total_ht_k')->label('HT')->size(TextSize::ExtraSmall),
                            ])
                            ->columns(5),
                    ]),
                Section::make('TVA')
                    ->collapsible()
                    ->visible(fn (Declaration $record): bool => $record->type === Declaration::TYPE_VAT)
                    ->schema([
                        RepeatableEntry::make('vat_client_invoices')
                            ->hiddenLabel()
                            ->state(fn (Declaration $record): array => $this->clientInvoices($record))
                            ->schema([
                                TextEntry::make('payed_at')->label('Payée')->size(TextSize::ExtraSmall),
                                TextEntry::make('code')->label('N°')->html()->wrap()->size(TextSize::ExtraSmall)->columnSpan(2),
                                TextEntry::make('client_slug')->label('Clt')->html()->size(TextSize::ExtraSmall),
                                TextEntry::make('total_ht_k')->label('HT')->size(TextSize::ExtraSmall),
                                TextEntry::make('vat_collected')->label('TVA')->size(TextSize::ExtraSmall),
                            ])
                            ->columns(6),
                        RepeatableEntry::make('vat_supplier_invoices')
                            ->label('Fournisseurs')
                            ->state(fn (Declaration $record): array => $this->supplierInvoices($record))
                            ->schema([
                                TextEntry::make('invoice_at')->label('Facture')->size(TextSize::ExtraSmall),
                                TextEntry::make('number')->label('N°')->wrap()->size(TextSize::ExtraSmall)->columnSpan(2),
                                TextEntry::make('supplier')
                                    ->label('Four.')
                                    ->size(TextSize::ExtraSmall)
                                    ->limit(18)
                                    ->tooltip(fn (?string $state): ?string => $state)
                                    ->extraAttributes(['class' => 'whitespace-nowrap'])
                                    ->columnSpan(2),
                                TextEntry::make('amount')->label('Mt.')->size(TextSize::ExtraSmall)->columnSpan(1),
                                TextEntry::make('vat')->label('TVA')->size(TextSize::ExtraSmall)->columnSpan(1),
                            ])
                            ->columns(7),
                    ]),
            ]);
    }

    private function clientInvoices(Declaration $declaration): array
    {
        return Invoice::query()
            ->with('company')
            ->whereKey($declaration->calculation_details['client_invoice_ids'] ?? [])
            ->get()
            ->map(fn (Invoice $invoice): array => [
                // C'est bien la date de paiement qui fait entrer la facture dans la déclaration.
                'payed_at' => $invoice->payed_at?->format('d/m') ?? '—',
                'code' => new HtmlString(sprintf(
                    '<a href="%s" class="text-primary-600 hover:underline">%s</a>',
                    e(InvoiceResource::getUrl('edit', ['record' => $invoice])),
                    e($invoice->code ?: 'Facture #'.$invoice->getKey()),
                )),
                'client_slug' => $invoice->company
                    ? new HtmlString(sprintf(
                        '<a href="%s" class="text-primary-600 hover:underline">%s</a>',
                        e(route('filament.admin.crm.resources.companies.edit', ['record' => $invoice->company])),
                        e(strtoupper(substr($invoice->company->slug, 0, 3))),
                    ))
                    : 'N/A',
                'total_ht_k' => number_format((float) $invoice->total_ht / 1000, 1, ',', ' ').' K€',
                'vat_collected' => number_format((float) $invoice->tva, 2, ',', ' ').' €',
            ])
            ->all();
    }

    private function supplierInvoices(Declaration $declaration): array
    {
        return QontoSupplierInvoice::query()
            ->whereKey($declaration->calculation_details['qonto_supplier_invoice_ids'] ?? [])
            ->get()
            ->map(fn (QontoSupplierInvoice $invoice): array => [
                'invoice_at' => $invoice->invoice_at?->format('d/m') ?? '—',
                'number' => $invoice->number ?: 'Facture #'.$invoice->getKey(),
                'supplier' => $invoice->supplier_name ?: 'Fournisseur non défini',
                'amount' => number_format((float) ($invoice->account_amount ?? $invoice->amount ?? 0), 2, ',', ' ').' €',
                'vat' => number_format((float) ($invoice->account_vat ?? (($invoice->vat_cents ?? 0) / 100)), 2, ',', ' ').' €',
            ])
            ->all();
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
