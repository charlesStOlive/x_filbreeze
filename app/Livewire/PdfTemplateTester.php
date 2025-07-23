<?php 

namespace App\Livewire;

use App\Models\Company;
use App\Models\Invoice;
use Livewire\Component;
use App\Services\Pdf\Base\PdfRenderer;
use App\Services\Pdf\Base\PdfTemplateRegistry;


class PdfTemplateTester extends Component
{
    public string $key;
    public string $modelId;
    public string $renderedHtml = '';

    public function mount(string $key, string $modelId): void
    {
        $this->key = $key;
        $this->modelId = $modelId;

        $model = $this->resolveModelInstance($key, $modelId);

        $template = PdfTemplateRegistry::getTemplateInstance($key, $model);
        $this->renderedHtml = app(PdfRenderer::class)->render($template);
    }

    public function render()
    {
        return view('livewire.pdf-template-tester');
    }

    protected function resolveModelInstance(string $key, string $modelId): \Illuminate\Database\Eloquent\Model
    {
        return str_contains($key, 'invoice')
            ? Invoice::findOrFail($modelId)
            : Company::with('contacts')->findOrFail($modelId);
    }
}
