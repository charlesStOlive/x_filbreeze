<?php

namespace App\Services\Declarations;

use App\Models\Declaration;
use CharlesStOlive\FilamentQonto\Models\QontoTransaction;
use Illuminate\Support\Collection;

/**
 * Détecte, parmi les transactions Qonto synchronisées localement, le paiement d'une
 * déclaration déjà "Déclarée" et la fait passer en "Payée". La transition vers
 * "Déclarée" reste volontairement manuelle : on ne fait ici que constater un paiement.
 */
class DeclarationPaymentDetector
{
    /**
     * @return Collection<int, Declaration> les déclarations passées en "paid"
     */
    public function detect(): Collection
    {
        return collect([Declaration::TYPE_VAT, Declaration::TYPE_URSSAF])
            ->flatMap(fn (string $type): Collection => $this->detectForType($type))
            ->values();
    }

    /**
     * @return Collection<int, Declaration>
     */
    private function detectForType(string $type): Collection
    {
        $patterns = (array) config("qonto.forecast.taxes.{$type}.patterns", []);

        if ($patterns === []) {
            return collect();
        }

        $matchedTransactionIds = Declaration::query()
            ->whereNotNull('qonto_transaction_id')
            ->pluck('qonto_transaction_id')
            ->all();

        $updated = collect();

        Declaration::query()
            ->where('type', $type)
            ->where('status', 'filed')
            ->orderBy('period_start')
            ->get()
            ->each(function (Declaration $declaration) use ($patterns, &$matchedTransactionIds, $updated): void {
                $transaction = $this->findPaymentTransaction($declaration, $patterns, $matchedTransactionIds);

                if (! $transaction) {
                    return;
                }

                $declaration->forceFill([
                    'status' => 'paid',
                    'paid_at' => $transaction->settled_at ?: $transaction->emitted_at ?: now(),
                    'qonto_transaction_id' => $transaction->getKey(),
                ])->save();

                $matchedTransactionIds[] = $transaction->getKey();
                $updated->push($declaration);
            });

        return $updated;
    }

    /**
     * @param array<int, string> $patterns
     * @param array<int, int> $excludedTransactionIds
     */
    private function findPaymentTransaction(Declaration $declaration, array $patterns, array $excludedTransactionIds): ?QontoTransaction
    {
        // Le paiement ne peut survenir qu'après le dépôt de la déclaration (ou, à défaut
        // de date de dépôt connue, après la fin de la période concernée).
        $since = $declaration->filed_at ?? $declaration->period_end;

        return QontoTransaction::query()
            ->where('side', 'debit')
            ->when($excludedTransactionIds !== [], fn ($query) => $query->whereNotIn('id', $excludedTransactionIds))
            ->where(function ($query) use ($since): void {
                $query->where('settled_at', '>=', $since)
                    ->orWhere('emitted_at', '>=', $since);
            })
            ->orderByRaw('COALESCE(settled_at, emitted_at) asc')
            ->get()
            ->first(fn (QontoTransaction $transaction): bool => $this->matchesPatterns($transaction, $patterns));
    }

    /**
     * @param array<int, string> $patterns
     */
    private function matchesPatterns(QontoTransaction $transaction, array $patterns): bool
    {
        $text = strtolower(implode(' ', array_filter([
            $transaction->label,
            $transaction->counterparty_name,
            data_get($transaction->raw, 'reference'),
            implode(' ', array_column((array) data_get($transaction->raw, 'labels', []), 'name')),
        ])));

        if ($text === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
