<?php

namespace App\Casts\MsGraph;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Arr;

class EmailInCast implements CastsAttributes
{
    protected string $keyPath;

    public function __construct(string $keyPath)
    {
        $this->keyPath = $keyPath;
    }

    public function get($model, string $key, $value, array $attributes)
    {
        // Décoder `datas_metas` en tableau
        $data = json_decode($attributes['datas_metas'] ?? '{}', true);

        // Retourner la valeur ciblée
        return Arr::get($data, $this->keyPath, null);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        // Décoder `datas_metas` existant
        $data = json_decode($attributes['datas_metas'] ?? '{}', true);

        // Modifier la clé spécifique
        Arr::set($data, $this->keyPath, $value);

        // Réencoder et retourner la valeur pour stockage
        return json_encode($data);
    }
}
