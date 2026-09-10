<?php

namespace App\Filament\Widgets;

use App\Services\Declarations\DeclarationCalculator;
use CharlesStOlive\FilamentQonto\Services\BankAccountsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

class TreasuryOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Trésorerie';

    protected function getStats(): array
    {
        try {
            $account = app(BankAccountsService::class)->requireDefault();
        } catch (Throwable $exception) {
            return [
                Stat::make('Trésorerie', 'Erreur')
                    ->description($exception->getMessage())
                    ->color('danger'),
            ];
        }

        $currency = $account->currency ?: 'EUR';
        $treasuryCents = $account->balanceCents ?? 0;
        $dueCents = app(DeclarationCalculator::class)->outstandingStateDueCents();
        $forecastCents = $treasuryCents - $dueCents;

        return [
            Stat::make('Trésorerie', $this->money($treasuryCents, $currency))
                ->description($account->slug ?: $account->iban ?: $account->id),

            Stat::make('Dû à l’État', '- ' . $this->money($dueCents, $currency))
                ->description('TVA et URSSAF non payées')
                ->color('warning'),

            Stat::make('Trésorerie théorique', $this->money($forecastCents, $currency))
                ->description('Trésorerie une fois les déclarations en cours réglées')
                ->color($forecastCents >= 0 ? 'success' : 'danger'),
        ];
    }

    private function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' ' . $currency;
    }
}
