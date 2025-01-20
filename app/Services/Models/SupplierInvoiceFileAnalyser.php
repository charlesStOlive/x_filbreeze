<?php

namespace App\Services\Models;

use App\Dto\AnalyseResponse;
use App\Models\Supplier;
use App\Services\Processors\FileProcessor;
use App\Services\Ia\MistralAgentService;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ProcessorException;

class SupplierInvoiceFileAnalyser
{
    private FileProcessor $fileProcessor;
    private MistralAgentService $mistralAgent;
    private ?AnalyseResponse $response = null;
    public string $mistralPrompt = '';
    private const MAX_RETRY = 5;

    public function __construct(FileProcessor $fileProcessor, MistralAgentService $mistralAgent)
    {
        $this->fileProcessor = $fileProcessor;
        $this->mistralAgent = $mistralAgent;
    }

    /**
     * Analyse un fichier (PDF ou image) et retourne les résultats sous forme de DTO.
     *
     * @param string $filePath
     * @return AnalyseResponse
     */
    public function analyzeFile(string $filePath): AnalyseResponse
    {
        try {
            $suppliers = Supplier::pluck('name', 'id')->toArray();
            $result = $this->fileProcessor->processFile($filePath);

            $this->mistralPrompt = json_encode([
                'contenu' => $result['content'],
                'clients' => $suppliers
            ]);

            \Log::info($this->mistralPrompt);
            $response = $this->callAgentWithRetry();
            \Log::info($response);
            if($response['error'] ?? false) {
                return AnalyseResponse::error($response['error']);
            }
            return AnalyseResponse::success($response);
        } catch (ProcessorException $e) {
            Log::error('Analyse échouée : ' . $e->getMessage());
            return AnalyseResponse::error('Le fichier est vide.');
        } catch (\Exception $e) {
            Log::critical('Erreur inattendue : ' . $e->getMessage());
            return AnalyseResponse::error('Erreur inattendue : ' . $e->getMessage());
        }
    }

    

    /**
     * Appelle l'agent Mistral avec une logique de retry si la réponse JSON est invalide.
     *
     * @return array|null
     * @throws \RuntimeException
     */
    private function callAgentWithRetry(): ?array
    {
        $agentId = 'ag:3e2c948d:20241112:extraction-facture:4bb4eea5';
        $attempts = 0;

        do {
            $response = $this->mistralAgent->callAgent($agentId, $this->mistralPrompt);
            $decodedResponse = json_decode($response['choices'][0]['message']['content'] ?? '', true);
            

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedResponse;
            }
            Log::warning('Erreur JSON lors de l\'appel à Mistral. Tentative : ' . ($attempts + 1));
            Log::info($response);
            $attempts++;
        } while ($attempts < self::MAX_RETRY);

        throw new \RuntimeException('Erreur de décodage JSON après plusieurs tentatives : ' . json_last_error_msg());
    }
}
