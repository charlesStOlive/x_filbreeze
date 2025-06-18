<?php

namespace App\Services\Models;

use App\Dto\AnalyseResponse;
use App\Models\Supplier;
use App\Services\Ia\MistralAgentService;
use App\Services\Processors\FileProcessor;
use Illuminate\Support\Facades\Log;
use App\Exceptions\MistralException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ExtractSupplierInvoiceData
{
    public function __construct(
        protected FileProcessor $processor,
        protected MistralAgentService $mistral
    ) {}

    public function analyze(TemporaryUploadedFile $file): AnalyseResponse
    {
        try {
            $content = $this->processor->processFile($file->getRealPath());

            $prompt = json_encode([
                'contenu' => $content['content'],
                'clients' => Supplier::pluck('name', 'id')->toArray(),
            ]);

            $raw = $this->mistral->callAgent(self::AGENT_ID, $prompt);
            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                throw new MistralException("Réponse Mistral invalide");
            }

            return AnalyseResponse::success($decoded);

        } catch (MistralException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::critical("Erreur analyse IA : " . $e->getMessage());
            return AnalyseResponse::error("Erreur inattendue : " . $e->getMessage());
        }
    }

    private const AGENT_ID = 'ag:3e2c948d:20241112:extraction-facture:4bb4eea5';
}
