<?php

namespace App\Services\Processors\Emails;

trait EmailProcessorTrait
{
    /**
     * Retourne la clé de résultat pour ce service.
     */
    protected function getResultKey(): string
    {
        return 'services_results.' . static::getKey(); // Appelle la méthode imposée par l'interface
    }

    /**
     * Retourne la clé de service pour ce service.
     */
    protected function getServiceKey(): string
    {
        return 'services_options.' . static::getKey();
    }

    /**
     * Définit une erreur dans les résultats du service.
     */
    protected function setError($reason): void
    {
        $this->email->setAttribute($this->getResultKey() . '.success', false);
        $this->email->setAttribute($this->getResultKey() . '.reason', $reason);
    }

    /**
     * Définit un résultat spécifique dans les résultats du service.
     */
    protected function setResult(string $keyName, string|array $value): void
    {
        $this->email->setAttribute($this->getResultKey() . '.' . $keyName, $value);
    }

    /**
     * Récupère un résultat spécifique dans les résultats du service.
     */
    protected function getResult(string $keyName): string
    {
        return $this->email->getAttribute($this->getResultKey() . '.' . $keyName);
    }

    /**
     * Récupère une option spécifique du service.
     */
    protected function getService(string $keyName): string
    {
        return $this->email->getAttribute($this->getServiceKey() . '.' . $keyName);
    }
}
