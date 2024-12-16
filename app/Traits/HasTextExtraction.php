<?php

namespace App\Traits;

use Illuminate\Support\Arr;

trait HasTextExtraction
{
    /**
     * Extraire les textes en fonction de la configuration $getTextes.
     *
     * @return array
     */
    public function extractTextToJson(): array
    {
        if (!isset($this->getTextes) || empty($this->getTextes)) {
            throw new \Exception('The $getTextes property is not defined or empty in the model.');
        }

        $result = [];

        foreach ($this->getTextes as $path) {
            // Gestion des wildcards (*)
            if (str_contains($path, '*')) {
                $matches = Arr::dot($this->attributesToArray());
                $keys = Arr::where(array_keys($matches), fn($key) => fnmatch($path, $key));
                foreach ($keys as $matchedKey) {
                    Arr::set($result, $matchedKey, Arr::get($this->attributesToArray(), $matchedKey));
                }
            } else {
                // Gestion des chemins simples
                Arr::set($result, $path, Arr::get($this->attributesToArray(), $path));
            }
        }

        return $result;
    }


    public function injectTextFromJson(array $data): void
    {
        $attributesToArray = $this->attributesToArray();

        if (!isset($this->getTextes) || empty($this->getTextes)) {
            throw new \Exception('The $getTextes property is not defined or empty in the model.');
        }

        foreach ($this->getTextes as $path) {
            // Gestion des wildcards (*)
            if (str_contains($path, '*')) {
                $matches = Arr::dot($attributesToArray);

                // Récupérer toutes les clés correspondantes
                $keys = Arr::where(array_keys($matches), fn($key) => fnmatch($path, $key));

                foreach ($keys as $matchedKey) {
                    $value = Arr::get($data, $matchedKey);

                    if ($value !== null) {
                        // Gestion spécifique pour les items ou structures imbriquées
                        $attributeKey = explode('.', $matchedKey)[0];
                        if ($this->isJsonCastable($attributeKey)) {
                            $this->{$attributeKey} = $this->recursiveMerge(
                                $this->{$attributeKey},
                                $data[$attributeKey] ?? []
                            );
                        } else {
                            Arr::set($this->attributes, $matchedKey, $value);
                        }
                    }
                }
            } else {
                // Gestion des chemins simples
                $value = Arr::get($data, $path);

                if ($value !== null) {
                    $attributeKey = explode('.', $path)[0];

                    if ($this->isJsonCastable($attributeKey)) {
                        $this->{$attributeKey} = $this->recursiveMerge(
                            $this->{$attributeKey},
                            $data[$attributeKey] ?? []
                        );
                    } else {
                        Arr::set($this->attributes, $path, $value);
                    }
                }
            }
        }

       \Log::info('injectTextFromJson', $this->attributes);
    }

    /**
     * Fusionne les données récursivement en conservant les champs existants.
     *
     * @param mixed $current L'état actuel du champ (array ou JSON)
     * @param array $new Les nouvelles données à fusionner
     * @return array
     */
    protected function recursiveMerge($current, array $new): array
    {
        if (is_string($current)) {
            $current = json_decode($current, true) ?? [];
        }

        if (!is_array($current)) {
            $current = [];
        }

        foreach ($new as $key => $value) {
            if (is_array($value) && isset($current[$key]) && is_array($current[$key])) {
                // Fusionner récursivement les sous-éléments
                $current[$key] = $this->recursiveMerge($current[$key], $value);
            } else {
                // Remplacer ou ajouter l'élément
                $current[$key] = $value;
            }
        }

        return $current;
    }

    /**
     * Vérifie si un champ est casté en JSON ou array.
     *
     * @param string $key Le nom de l'attribut
     * @return bool
     */
    protected function isJsonCastable($key): bool
    {
        $casts = $this->getCasts();
        return isset($casts[$key]) && in_array($casts[$key], ['array', 'json']);
    }

    /**
     * Fusionne les nouvelles données dans un tableau existant.
     *
     * @param mixed $current L'état actuel du champ
     * @param array $new Les nouvelles données à fusionner
     * @return array
     */
    protected function mergeIntoArray($current, array $new): array
    {
        if (is_string($current)) {
            $current = json_decode($current, true) ?? [];
        }

        if (!is_array($current)) {
            $current = [];
        }

        return array_merge($current, $new);
    }
}
