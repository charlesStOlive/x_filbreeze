<?php

namespace App\Services\Processors\Emails;

use App\Models\MsgEmailDraft;

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
        $this->email->state = 'end';
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

    protected function setRegexKeyWorking(): string
    {
        $newBody = $this->insertInRegexKey('Je travaille');
        return $newBody;
    }

    /**
     * Récupère une option spécifique du service.
     */
    protected function getService(string $keyName): string
    {
        return $this->email->getAttribute($this->getServiceKey() . '.' . $keyName);
    }

    public function launchStartingState(): bool
    {
        $newBody = $this->setRegexKeyWorking();

        try {
            //LOGIQUE POUR FAIRE UN UPDATE EMAIL SUR LA BASE DU USER EMAIL et de newBody 
            $this->email->state = 'running';
            return true;
        } catch (\Exception $ex) {
            $this->email->state = 'error';
            $this->email->errors = $ex->getMessage();
            return false;
        }
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
}
