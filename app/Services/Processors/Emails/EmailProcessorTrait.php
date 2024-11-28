<?php

namespace App\Services\Processors\Emails;

use App\Models\MsgEmailDraft;
use App\Services\MsGraph\MsGraphEmailService;

trait EmailProcessorTrait
{
    protected function resolveEmailService(): MsGraphEmailService
    {
        return app(MsGraphEmailService::class);
    }
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
        $this->email->status = 'end';
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
    protected function getResult(string $keyName): string|array
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

    

    protected function setRegexKeyWorking(): string
    {
        $newBody = $this->insertInRegexKey('Je travaille');
        return $newBody;
    }


    public function replaceRegexKey(string $replacement): string
    {

        $pattern = '/##\s*(.+?)\s*##/';
        // Remplacement de la première occurrence, sans conserver les balises ##
        $newBody = preg_replace($pattern, $replacement, $this->emailData->bodyOriginal, 1);
        return $newBody;
    }

    public function insertInRegexKey(string $replacement): string
    {
        // Expression régulière pour trouver ## {contenu potentiel} ##
        $pattern = '/##\s*(.+?)\s*##/';
        // Remplacement de la première occurrence, en conservant les balises ##
        $newBody = preg_replace($pattern, "## {$replacement} ##", $this->emailData->bodyOriginal, 1);
        return $newBody;
    }

    public function removeRegexKeyAndLineIfEmptyHTML(string $htmlText): string
    {
        // Expression régulière pour trouver ## {contenu potentiel} ##
        $pattern = '/##\s*(.+?)\s*##/';

        // Diviser le contenu HTML en lignes
        $lines = preg_split('/\r\n|\r|\n/', $htmlText);

        // Parcourir chaque ligne pour traiter le modèle et les lignes devenues vides
        foreach ($lines as $index => $line) {
            // Supprimer le contenu correspondant au modèle
            $lineWithoutKey = preg_replace($pattern, '', $line);

            // Si la ligne devient vide après suppression du modèle, on la supprime
            if (trim($lineWithoutKey) === '') {
                $lines[$index] = null;
            } else {
                $lines[$index] = $lineWithoutKey; // Sinon, conserver la ligne nettoyée
            }
        }

        // Reconstruire le contenu HTML tout en conservant les autres lignes intactes
        return implode("\n", array_filter($lines, fn($line) => $line !== null));
    }

}
