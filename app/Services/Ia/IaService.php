<?php

namespace App\Services\Ia;

use App\Exceptions\MistralException;
use Illuminate\Support\Facades\Log;

class IaService
{
    protected MistralAgentService $mistralService;

    public function __construct(MistralAgentService $mistralService)
    {
        $this->mistralService = $mistralService;
    }

    /**
     * Corrige l'orthographe via un agent Mistral
     */
    public function correctText(string $jsonText): string
    {
        $agentId = config('services.mistral.agents.correction_orthographe');

        return $this->callAgent($agentId, $jsonText);
    }

    /**
     * Analyse une facture fournisseur
     */
    public function analyzeSupplierInvoice(string $content): string
    {
        $agentId = config('services.mistral.agents.supplier_invoice_analysis');

        return $this->callAgent($agentId, $content);
    }

    /**
     * Méthode générique pour appeler un agent
     */
    public function callAgent(string $agentId, string $message, array $additionalParams = []): string
    {
        try {
            return $this->mistralService->callAgent($agentId, $message, $additionalParams);
        } catch (MistralException $e) {
            Log::error('Erreur MistralException', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Erreur inattendue lors de l\'appel agent', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);
            throw new MistralException('Erreur inattendue : ' . $e->getMessage());
        }
    }
}
