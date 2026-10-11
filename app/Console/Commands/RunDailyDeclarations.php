<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * La tâche du matin des déclarations, dans l'ordre : synchroniser Qonto, ouvrir les déclarations
 * dont la période a commencé (le mois ou le trimestre en cours, selon config/declarations.php),
 * puis les recalculer sur les données fraîches. Une synchronisation en échec n'empêche pas
 * l'ouverture ni le recalcul : ils se font alors sur les données locales, et la tâche sort en échec.
 */
class RunDailyDeclarations extends Command
{
    protected $signature = 'declarations:daily';

    protected $description = 'Synchronise Qonto, ouvre les déclarations dues et les recalcule';

    public function handle(): int
    {
        $this->line('['.now()->toDateTimeString().'] Synchronisation Qonto');
        $synced = $this->call('qonto:sync') === self::SUCCESS;

        if (! $synced) {
            $this->warn('Synchronisation Qonto en échec : les déclarations sont calculées sur les données locales.');
        }

        $this->call('declarations:create-due');
        $this->call('declarations:refresh');

        return $synced ? self::SUCCESS : self::FAILURE;
    }
}
