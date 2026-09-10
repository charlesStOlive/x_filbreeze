<?php

namespace App\Console\Commands;

use App\Models\Declaration;
use App\Services\Declarations\DeclarationCalculator;
use App\Services\Declarations\DeclarationPaymentDetector;
use Illuminate\Console\Command;

/**
 * Recalcule chaque matin les déclarations automatiques pas encore déclarées, et
 * détecte via les transactions Qonto synchronisées si une déclaration déjà déclarée
 * vient d'être payée. Le passage à "Déclarée" reste une action manuelle.
 */
class RefreshDeclarationCalculations extends Command
{
    protected $signature = 'declarations:refresh';

    protected $description = "Recalcule les déclarations automatiques en cours et détecte les paiements Qonto des déclarations déclarées";

    public function handle(DeclarationCalculator $calculator, DeclarationPaymentDetector $paymentDetector): int
    {
        Declaration::query()
            ->where('status', 'draft')
            ->where('calculation_mode', Declaration::MODE_AUTOMATIC)
            ->get()
            ->each(function (Declaration $declaration) use ($calculator): void {
                $calculated = $calculator->calculate($declaration->type, $declaration->period_start);
                $declaration->forceFill($calculated)->save();

                $this->info("Déclaration #{$declaration->id} ({$declaration->type}) recalculée.");
            });

        $paymentDetector->detect()->each(function (Declaration $declaration): void {
            $this->info("Déclaration #{$declaration->id} ({$declaration->type}) marquée payée (transaction Qonto #{$declaration->qonto_transaction_id}).");
        });

        return self::SUCCESS;
    }
}
