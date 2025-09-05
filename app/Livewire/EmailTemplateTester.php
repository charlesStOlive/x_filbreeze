<?php 

namespace App\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use App\Models\Invoice;
use App\Models\Company;
use App\Services\MsGraph\EmailDraft\Base\EmailDraftTemplateRegistry;
use App\Services\MsGraph\EmailDraft\Base\EmailDraftRenderer;

class EmailTemplateTester extends Component
{
    public string $key;
    public string $modelId;
    public string $renderedHtml = '';

    public function mount(string $key, string $modelId): void
    {
        $this->key = $key;
        $this->modelId = $modelId;

        $model = $this->resolveModelInstance($key, $modelId);

        $template = EmailDraftTemplateRegistry::getTemplateInstance($key, $model);
        $this->renderedHtml = app(EmailDraftRenderer::class)->render($template)['body'];
    }

    public function render()
    {
        return view('livewire.email-template-tester');
    }

    protected function resolveModelInstance(string $key, string $modelId): Model
    {
        return str_contains($key, 'invoice')
            ? Invoice::findOrFail($modelId)
            : Company::with('contacts')->findOrFail($modelId);
    }
}
