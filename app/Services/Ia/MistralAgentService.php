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
        $this->apiUrl = config('services.mistral.api_url');
        $this->apiKey = config('services.mistral.api_key');

        // \Log::info("apiUrl".$this->apiUrl);
        // \Log::info("apiUrl 3 premier car".\Str::limit($this->apiKey, 3));

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
                // $this->logResponse($response);
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
