<?php

namespace App\Services\Document;

/**
 * Les droits du moteur d'édition : une permission par modèle d'édition, rangée par famille (filament-permission-manager).
 *
 *     crm.invoice.pdf.invoice_summary_pdf     un modèle PDF des factures
 *     crm.invoice.pdf.*                       toute la famille PDF des factures, y compris les modèles ajoutés plus tard
 *
 * Rien n'est listé à la main : les modèles viennent des registres (config templates-pdf, templates-email-draft,
 * templates-excel), par modèle Eloquent. Une Resource les expose par `permissionActions()` (permissions:sync les crée,
 * l'écran des rôles les montre) ; BaseDocumentAction ne propose que les modèles permis.
 */
class DocumentPermissions
{
    /** Famille => libellé dans l'écran des rôles. */
    public const FAMILIES = [
        'pdf' => 'Éditions PDF',
        'email' => 'Brouillons d’e-mails',
        'excel' => 'Exports Excel',
    ];

    /** Famille => fichier de config de son registre. */
    public const REGISTRIES = [
        'pdf' => 'templates-pdf',
        'email' => 'templates-email-draft',
        'excel' => 'templates-excel',
    ];

    /**
     * Les permissions d'édition d'un modèle : `{famille}.{clé du modèle d'édition}` => libellé.
     *
     * @param  class-string  $model
     * @return array<string, string>
     */
    public static function actionsFor(string $model): array
    {
        $actions = [];

        foreach (self::REGISTRIES as $family => $config) {
            $type = config("{$config}.types.{$model}");

            if ($type === null) {
                continue;
            }

            foreach ((array) config("{$config}.{$type}.templates", []) as $template) {
                $actions[self::permission($family, $template)] = (string) $template::label();
            }
        }

        return $actions;
    }

    /** `pdf.invoice_summary_pdf` : la permission (sans le préfixe de la liste) d'un modèle d'édition. */
    public static function permission(string $family, string $template): string
    {
        return "{$family}.".$template::key();
    }
}
