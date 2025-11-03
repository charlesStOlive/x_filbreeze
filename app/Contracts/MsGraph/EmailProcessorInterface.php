<?php

namespace App\Contracts\MsGraph;

interface EmailProcessorInterface
{
    /**
     * Clé unique du service.
     */
    public static function getKey(): string;

    /**
     * Label affiché dans l'interface.
     */
    public static function getLabel(): string;

    /**
     * Description du service.
     */
    public static function getDescription(): string;

    /**
     * Formulaire Filament pour configurer le service.
     * 
     * @return array Array de composants Filament
     */
    public static function getForm(): array;

    /**
     * Valeurs par défaut pour les options du service.
     */
    public static function getDefaults(): array;
}
