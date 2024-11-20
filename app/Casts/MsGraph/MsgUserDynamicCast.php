<?php 

namespace App\Casts\MsGraph;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Arr;

class MsgUserDynamicCast implements CastsAttributes
{
    protected string $processor;
    protected string $option;

    public function __construct(string $processor, string $option)
    {
        $this->processor = $processor;
        $this->option = $option;
    }

    public function get($model, string $key, $value, array $attributes)
    {
        // Décoder les services JSON en tableau
        $data = $this->decodeServices($attributes['services'] ?? '{}');

        // Récupérer la valeur cible ou la valeur par défaut depuis la config
        return Arr::get($data, "{$this->processor}.{$this->option}", $this->getDefaultValue());
    }

    public function set($model, string $key, $value, array $attributes)
    {
        // Décoder les services JSON en tableau
        $data = $this->decodeServices($attributes['services'] ?? '{}');

        // Modifier la clé ciblée
        Arr::set($data, "{$this->processor}.{$this->option}", $value);

        // Retourner le JSON encodé pour le stockage
        return [
            'services' => json_encode($data),
        ];
    }

    /**
     * Décode la chaîne JSON et retourne un tableau.
     */
    protected function decodeServices(string $json): array
    {
        $data = json_decode($json, true);

        // Retourne un tableau vide si la décodage échoue
        return is_array($data) ? $data : [];
    }

    /**
     * Récupère la valeur par défaut depuis la configuration.
     */
    protected function getDefaultValue()
    {
        return config("msgraph.services.{$this->processor}.options.{$this->option}.default");
    }
}
