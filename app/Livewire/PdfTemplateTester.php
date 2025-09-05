<?php

namespace App\Livewire;

use Log;
use Livewire\Component;
use Illuminate\Support\Str;
use App\Services\Pdf\Base\PdfRenderer;
use App\Services\Pdf\Base\PdfTemplateRegistry;
use Illuminate\Database\Eloquent\Model;

class PdfTemplateTester extends Component
{
    public string $modelclass;   // ex: "invoice_supplier"
    public string $key;          // ex: "base"
    public string $modelId;      // ex: "12"
    public string $renderedHtml = '';

    public function mount(string $modelclass, string $templateKey, int $modelId): void
    {
        $this->modelclass = $modelclass;
        $this->key = $templateKey;
        $this->modelId = $modelId;

        $modelInstance = $this->resolveModelInstance($modelclass, $modelId);
        $modelType = PdfTemplateRegistry::resolveModelTypeFromRecord($modelInstance); // ex: "invoice_supplier"

        // Cherche le template dans les templates enregistrés pour ce type
        $templateClass = collect(PdfTemplateRegistry::getTemplatesFor($modelType))
            ->first(fn($cls) => $cls::key() === $templateKey);

        Log::info(PdfTemplateRegistry::getTemplatesFor($modelType));
        Log::info($templateClass);

        if (! $templateClass) {
            abort(500, "Template PDF [{$templateKey}] introuvable pour le modèle [{$modelType}].");
        }

        $templateInstance = new $templateClass($modelInstance);
        $this->renderedHtml = app(PdfRenderer::class)->render($templateInstance);
    }

    public function render()
    {
        return view('livewire.pdf-template-tester');
    }

    protected function resolveModelInstance(string $modelclass, string $modelId): Model
    {
        $class = $this->getModelClass($modelclass);

        if (!class_exists($class)) {
            abort(404, "Le modèle [{$modelclass}] est introuvable (classe {$class} absente).");
        }

        if (!is_subclass_of($class, Model::class)) {
            abort(500, "La classe [{$class}] n'est pas un modèle Eloquent valide.");
        }

        $model = $class::find($modelId);

        if (! $model) {
            abort(404, "Aucun enregistrement [ID {$modelId}] trouvé pour le modèle [{$modelclass}].");
        }

        return $model;
    }

    protected function getModelClass(string $modelclass): string
    {
        return 'App\\Models\\' . Str::studly($modelclass);
    }
}
