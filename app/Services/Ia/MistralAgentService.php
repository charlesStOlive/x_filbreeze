<?php

namespace App\Services\Ia;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\MistralException;

class MistralAgentService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected Client $client;

    public function __construct()
    {
        $this->apiUrl = config('services.mistral.api_url');
        $this->apiKey = config('services.mistral.api_key');

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function callAgent(string $agentId, string $message, array $additionalParams = []): string
    {
        $payload = array_merge([
            'agent_id' => $agentId,
            'messages' => [
                ['role' => 'user', 'content' => $message]
            ],
            'n' => 1,
        ], $additionalParams);

        try {
            $response = $this->client->post('/v1/agents/completions', [
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['choices'][0]['message']['content'])) {
                throw new MistralException("Contenu manquant dans la réponse de Mistral.");
            }

            return $body['choices'][0]['message']['content'];

        } catch (RequestException $e) {
            $msg = $e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : $e->getMessage();

            Log::error('Erreur Mistral', [
                'message' => $e->getMessage(),
                'response' => $msg,
            ]);

            throw new MistralException('Erreur HTTP Mistral : ' . $msg);
        } catch (\Throwable $e) {
            throw new MistralException('Erreur inattendue Mistral : ' . $e->getMessage());
        }
    }
}
