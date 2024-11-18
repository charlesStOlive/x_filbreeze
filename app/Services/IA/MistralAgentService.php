<?php

namespace App\Services\Ia;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MistralAgentService
{
    protected $apiUrl;
    protected $apiKey;
    protected $client;

    public function __construct()
    {
        $this->apiUrl = env('MISTRAL_API_URL');
        $this->apiKey = env('MISTRAL_API_KEY');
        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function callAgent(string $agentId, string $messages, array $additionalParams = [])
    {
        try {
            // Assurez-vous que les messages sont sous forme de liste
            $data = array_merge([
                'agent_id' => $agentId,
                "stop" => "string",
                "n" => 1,
                'messages' => [
                    [
                        "role" =>  "user",
                        'content' => $messages
                    ]

                ],
            ], $additionalParams);

            $response = $this->client->post("/v1/agents/completions", [
                'json' => $data,
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->logResponse($response);
                return json_decode($response->getBody(), true);
            }

            \Log::error('Guzzle RequestException', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function callChatCompletion(string $model, string $message)
    {
        try {
            $data = [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $message,
                    ],
                ],
            ];

            \Log::info('Calling chat completion', ['url' => "/v1/chat/completions", 'data' => $data]);

            $response = $this->client->post("/v1/chat/completions", [
                'json' => $data,
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->logResponse($response);
                return json_decode($response->getBody(), true);
            }

            \Log::error('Guzzle RequestException', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}
