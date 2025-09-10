<?php

namespace App\Services\Document\Filament\Actions;

use Filament\Actions\Action;
use RuntimeException;

/**
 * Action de base pour les documents qui ne nécessitent pas d'enregistrement spécifique
 * (comme les imports/exports de listes)
 */
abstract class BaseDocumentListAction extends Action
{
    protected ?array $templates = null;

    /**
     * Définir les templates disponibles pour cette action
     */
    public function templates(array $templates): static
    {
        $this->templates = $templates;
        return $this;
    }

    /**
     * Obtenir les templates disponibles 
     */
    protected function getTemplates(): array
    {
        if ($this->templates === null) {
            throw new RuntimeException('Aucun template défini. Utilisez ->templates([...]) pour définir les templates disponibles.');
        }

        return $this->templates;
    }

    /**
     * Obtenir le template par défaut
     */
    protected function getDefaultTemplate(): string
    {
        $availableTemplates = $this->getTemplates();
        
        if (empty($availableTemplates)) {
            throw new RuntimeException('Aucun template disponible');
        }

        // Prendre le premier template de la liste comme défaut
        return $availableTemplates[0];
    }

    /**
     * Obtenir une instance de template par sa clé
     */
    protected function getTemplateInstance(string $key, ?array $options = null): mixed
    {
        $templateClass = collect($this->getTemplates())
            ->first(fn($cls) => $cls::key() === $key);

        if (!$templateClass) {
            throw new RuntimeException("Template avec la clé '{$key}' introuvable");
        }

        // Pour les actions de liste, on passe null comme record ou une collection
        return new $templateClass(null, $options);
    }

    /**
     * Méthode abstraite que chaque service doit implémenter pour définir son schéma spécifique
     */
    abstract protected function getServiceSchema(): array;

    /**
     * Méthode abstraite que chaque service doit implémenter pour gérer l'action
     */
    abstract protected function handleAction(array $data): mixed;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalWidth('md')
            ->fillForm(function () {
                $defaultTemplate = $this->getDefaultTemplate();
                
                return [
                    'template' => $defaultTemplate::key(),
                    'template_options' => $defaultTemplate::getDefaultOptions(),
                ];
            })
            ->schema(fn() => $this->getServiceSchema())
            ->action(fn(array $data) => $this->handleAction($data));
    }
}
