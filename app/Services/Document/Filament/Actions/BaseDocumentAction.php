<?php

namespace App\Services\Document\Filament\Actions;

use Filament\Actions\Action;
use RuntimeException;

abstract class BaseDocumentAction extends Action
{
    protected ?array $templates = null;
    protected mixed $contextRecord = null;
    protected mixed $contextQuery = null;

    /**
     * Définir les templates disponibles pour cette action
     */
    public function templates(array $templates): static
    {
        $this->templates = $templates;
        return $this;
    }

    /**
     * Définir un record spécifique pour cette action
     */
    public function withRecord(mixed $record): static
    {
        $this->contextRecord = $record;
        return $this;
    }

    /**
     * Définir une query spécifique pour cette action
     */
    public function withQuery(mixed $query): static
    {
        $this->contextQuery = $query;
        return $this;
    }

    /**
     * Obtenir les templates disponibles pour un enregistrement donné
     */
    protected function getTemplatesForRecord($record = null): array
    {
        if ($this->templates === null) {
            throw new RuntimeException('Aucun template défini. Utilisez ->templates([...]) pour définir les templates disponibles.');
        }

        $this->validateTemplateCompatibility();

        return $this->templates;
    }

    /**
     * Valider que tous les templates sont compatibles avec ce service
     */
    protected function validateTemplateCompatibility(): void
    {
        $expectedType = $this->getExpectedTemplateType();
        $incompatibleTemplates = [];

        foreach ($this->templates as $templateClass) {
            if (!is_subclass_of($templateClass, $expectedType)) {
                $incompatibleTemplates[] = $templateClass;
            }
        }

        if (!empty($incompatibleTemplates)) {
            $serviceName = class_basename(static::class);
            $incompatibleNames = collect($incompatibleTemplates)->map(fn($cls) => class_basename($cls))->join(', ');
            
            throw new RuntimeException(
                "Templates incompatibles détectés dans {$serviceName}. " .
                "Attendu: {$expectedType}, " .
                "Reçu: {$incompatibleNames}. " .
                "Vérifiez que vos templates héritent de la bonne classe de base."
            );
        }
    }

    /**
     * Obtenir le template par défaut pour un enregistrement donné
     */
    protected function getDefaultTemplateForRecord($record = null): string
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
    protected function getTemplateInstance(string $key, $record = null, ?array $options = null): mixed
    {
        // Ordre de priorité : paramètre $record > contextRecord > contextQuery > record de l'action
        $finalRecord = $record ?? $this->contextRecord ?? $this->contextQuery ?? $this->getRecord();
        
        $templateClass = collect($this->getTemplatesForRecord($finalRecord))
            ->first(fn($cls) => $cls::key() === $key);

        if (!$templateClass) {
            throw new RuntimeException("Template avec la clé '{$key}' introuvable");
        }

        return new $templateClass($finalRecord, $options);
    }

    /**
     * Méthode abstraite que chaque service doit implémenter pour définir son schéma spécifique
     */
    abstract protected function getServiceSchema($record = null): array;

    /**
     * Méthode abstraite que chaque service doit implémenter pour gérer l'action
     */
    abstract protected function handleAction(array $data, $record = null): mixed;

    /**
     * Méthode abstraite pour définir le type de template attendu par ce service
     */
    abstract protected function getExpectedTemplateType(): string;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalWidth('7xl')
            ->fillForm(function ($record) {
                $finalRecord = $this->contextRecord ?? $this->contextQuery ?? $record;
                $defaultTemplate = $this->getDefaultTemplateForRecord($finalRecord);
                
                return [
                    'template' => $defaultTemplate::key(),
                    'template_options' => $defaultTemplate::getDefaultOptions(),
                ];
            })
            ->schema(fn($record) => $this->getServiceSchema($this->contextRecord ?? $this->contextQuery ?? $record))
            ->action(fn(array $data, $record) => $this->handleAction($data, $this->contextRecord ?? $this->contextQuery ?? $record));
    }
}
