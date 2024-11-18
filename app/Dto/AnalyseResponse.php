<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class AnalyseResponse extends Data
{
    /**
     * Indique si l'analyse a réussi.
     */
    public function __construct(
        public bool $success,
        public ?array $data = null,
        public ?string $message = null
    ) {}

    /**
     * Vérifie si l'analyse est un succès.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Retourne le message d'erreur ou de succès.
     */
    public function getMessage(): string
    {
        return $this->message ?? ($this->success ? 'Analyse réussie.' : 'Une erreur est survenue.');
    }

    /**
     * Retourne les données sous forme de tableau.
     */
    public function getDataArray(): array
    {
        return $this->data ?? [];
    }

    /**
     * Retourne les données sous forme de strin.
     */
    public function getData(): string|null
    {
        return $this->data ?? null;
    }

    /**
     * Renvoie une instance de réponse pour un succès.
     */
    public static function success(array $data): self
    {
        return new self(true, $data, null);
    }

    /**
     * Renvoie une instance de réponse pour une erreur.
     */
    public static function error(string $message, ?array $data = null): self
    {
        return new self(false, $data, $message);
    }
}
