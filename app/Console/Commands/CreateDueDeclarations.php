<?php

namespace App\Console\Commands;

use App\Models\Declaration;
use App\Services\Declarations\DeclarationCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Ouvre automatiquement les déclarations dont la période a commencé (TVA au 1er de
 * chaque mois, URSSAF au 1er jour de sa propre période) au lieu de compter sur une
 * création manuelle mois par mois. Le bouton "Créer une déclaration" reste disponible
 * pour (re)générer une période particulière si besoin.
 */
class CreateDueDeclarations extends Command
{
    protected $signature = 'declarations:create-due';

    protected $description = 'Crée automatiquement les déclarations TVA/URSSAF dont la période a commencé';

    public function handle(DeclarationCalculator $calculator): int
    {
        foreach ([Declaration::TYPE_VAT, Declaration::TYPE_URSSAF] as $type) {
            $this->createDueForType($type, $calculator);
        }

        return self::SUCCESS;
    }

    private function createDueForType(string $type, DeclarationCalculator $calculator): void
    {
        $today = Carbon::today()->toDateString();

        // Garde-fou anti-boucle infinie : next() avance toujours d'une période à partir
        // de la dernière déclaration existante, donc ceci ne boucle que le temps de
        // rattraper les périodes manquantes.
        for ($i = 0; $i < 24; $i++) {
            $preview = $calculator->next($type);

            if ($preview['period_start'] > $today) {
                return;
            }

            $exists = Declaration::query()
                ->where('type', $type)
                ->where('period_start', $preview['period_start'])
                ->where('period_end', $preview['period_end'])
                ->exists();

            if ($exists) {
                return;
            }

            $declaration = Declaration::create(array_merge($preview, [
                'calculation_mode' => Declaration::MODE_AUTOMATIC,
            ]));

            $this->info(sprintf(
                'Déclaration %s créée : %s → %s.',
                $type,
                $declaration->period_start->toDateString(),
                $declaration->period_end->toDateString(),
            ));
        }
    }
}
