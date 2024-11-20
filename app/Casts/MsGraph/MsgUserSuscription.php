<?php 

namespace App\Casts\MsGraph;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Arr;

class MsgUserSuscription implements CastsAttributes
{
    protected string $keyPath;

    public function __construct(string $keyPath)
    {
        $this->keyPath = $keyPath;
    }

    public function get($model, string $key, $value, array $attributes)
    {
        // Décoder `suscriptions` en tableau
        $data = json_decode($attributes['services'] ?? '{}', true);

        // Retourner la valeur ciblée
        return (bool) Arr::get($data, $this->keyPath, false);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        // Décoder `suscriptions` en tableau
        $data = json_decode($attributes['services'] ?? '{}', true);

        // Modifier la clé ciblée
        Arr::set($data, $this->keyPath, (bool) $value);

        // Retourner le JSON encodé pour le stockage
        return [
            'services' => json_encode($data),
        ];
    }
}
