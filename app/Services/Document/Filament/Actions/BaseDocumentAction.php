<?php

namespace App\Services\Document\Filament\Actions;

use Filament\Actions\Action;
use RuntimeException;

abstract class BaseDocumentAction extends Action
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
     * Obtenir les templates disponibles pour un enregistrement donné
     */
    protected function getTemplatesForRecord($record): array
    {
        if ($this->templates === null) {
            throw new RuntimeException('Aucun template défini. Utilisez ->templates([...]) pour définir les templates disponibles.');
        }

        return $this->templates;
    }

    /**
     * Obtenir le template par défaut pour un enregistrement donné
     */
    protected function getDefaultTemplateForRecord($record): string
    {
        $availableTemplates = $this->getTemplatesForRecord($record);
        
        if (empty($availableTemplates)) {
            throw new RuntimeException('Aucun template disponible pour ce type d\'enregistrement');
        }

        // Prendre le premier template de la liste comme défaut
        return $availableTemplates[0];
    }

    /**
     * Obtenir une instance de template par sa clé
     */
    protected function getTemplateInstance(string $key, $record, ?array $options = null): mixed
    {
        $templateClass = collect($this->getTemplatesForRecord($record))
            ->first(fn($cls) => $cls::key() === $key);

        if (!$templateClass) {
            throw new RuntimeException("Template avec la clé '{$key}' introuvable");
        }

        return new $templateClass($record, $options);
    }

    /**
     * Méthode abstraite que chaque service doit implémenter pour définir son schéma spécifique
     */
    abstract protected function getServiceSchema($record): array;

    /**
     * Méthode abstraite que chaque service doit implémenter pour gérer l'action
     */
    abstract protected function handleAction(array $data, $record): mixed;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalWidth('7xl')
            ->fillForm(function ($record) {
                $defaultTemplate = $this->getDefaultTemplateForRecord($record);
                
                return [
                    'template' => $defaultTemplate::key(),
                    'template_options' => $defaultTemplate::getDefaultOptions(),
                ];
            })
            ->schema(fn($record) => $this->getServiceSchema($record))
            ->action(fn(array $data, $record) => $this->handleAction($data, $record));
    }
}
