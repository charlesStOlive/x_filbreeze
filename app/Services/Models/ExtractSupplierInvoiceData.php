<?php

namespace App\Services\Models;

use App\Services\Ia\IaService;
use App\Exceptions\MistralException;
use App\Services\Processors\FileProcessor;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ExtractSupplierInvoiceData
{
    protected IaService $iaService;
    protected FileProcessor $processor;

    public function __construct(IaService $iaService, FileProcessor $processor)
    {
        $this->iaService = $iaService;
        $this->processor = $processor;
    }

    public function analyze(TemporaryUploadedFile $file): AnalysisResult
    {
        try {
            // Préparer le contenu du fichier pour l'analyse
            $content = $this->prepareFileContent($file);

            // Appeler l'IA pour analyser
            $response = $this->iaService->analyzeSupplierInvoice($content);

            // Parser la réponse
            $data = $this->parseResponse($response);

            return AnalysisResult::success($data);
        } catch (MistralException $e) {
            return AnalysisResult::error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Erreur lors de l\'analyse de facture', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
            return AnalysisResult::error('Erreur interne : ' . $e->getMessage());
        }
    }

    protected function prepareFileContent(TemporaryUploadedFile $file): string
    {
        try {
            $content = $this->processor->processFile($file->getRealPath());

            $prompt = json_encode([
                'contenu' => $content['content'],
                'clients' => Supplier::pluck('name', 'id')->toArray(),
            ]);

            return $prompt;
        } catch (\Throwable $e) {
            throw new MistralException('Erreur lors du traitement du fichier : ' . $e->getMessage());
        }
    }

    protected function parseResponse(string $response): array
    {
        // Logique pour parser la réponse de l'IA
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new MistralException('Réponse IA invalide : format JSON incorrect');
        }

        return $data;
    }
}
