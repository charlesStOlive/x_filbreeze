<?php 

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SchemaServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Ajouter une méthode createMQY à Blueprint
        Blueprint::macro('createMQY', function (string $column) {
            // Récupérer le nom de la table
            $table = $this->getTable();

            // Vérifier si la colonne source existe
            // if (!Schema::hasColumn($table, $column)) {
            //     throw new \RuntimeException("La colonne `{$column}` n'existe pas dans la table `{$table}`.");
            // }

            // Créer les colonnes calculées pour MY (ANNEE_MOIS) et QY (ANNEE_QUARTER)
            $this->string("{$column}_my",10)
                ->storedAs("DATE_FORMAT({$column}, '%Y_%m')")
                ->nullable();

            $this->string("{$column}_qy",10)
                ->storedAs("CONCAT(YEAR({$column}), '_Q', CEIL(MONTH({$column}) / 3))")
                ->nullable();

            // Ajouter des index pour les colonnes
            $this->index(["{$column}_my"], "{$column}_my_index");
            $this->index(["{$column}_qy"], "{$column}_qy_index");
        });

        // Ajouter une méthode deleteMQY à Blueprint
        Blueprint::macro('deleteMQY', function (string $column) {
            // Supprimer les index associés
            $this->dropIndex("{$column}_my_index");
            $this->dropIndex("{$column}_qy_index");

            // Supprimer les colonnes calculées
            $this->dropColumn("{$column}_my");
            $this->dropColumn("{$column}_qy");
        });
    }

    public function register()
    {
        // Rien à enregistrer ici
    }
}
