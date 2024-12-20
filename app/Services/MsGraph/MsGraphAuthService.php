<?php namespace App\Services\MsGraph;

use Exception;
use GuzzleHttp\Client;
use App\Models\MsgToken;
use App\Models\MsgUserDraft;
use App\Models\MsgUserIn;

class MsGraphAuthService
{
    public static string $baseUrl = 'https://graph.microsoft.com/v1.0/';

    public function isConnected(): bool
    {
        $token = $this->getTokenData();
        return $token !== null && $token->expires >= time();
    }

    public function connect(bool $redirect = true): mixed
    {
        $params = [
            'scope' => 'https://graph.microsoft.com/.default',
            'client_id' => config('msgraph.clientId'),
            'client_secret' => config('msgraph.clientSecret'),
            'grant_type' => 'client_credentials',
        ];

        try {
            $client = new Client();
            $response = $client->post(config('msgraph.tenantUrlAccessToken'), ['form_params' => $params]);
            $token = json_decode($response->getBody()->getContents());

            if (isset($token->access_token)) {
                $this->storeToken($token->access_token, '', $token->expires_in);
            }

            return $redirect ? redirect(config('msgraph.msgraphLandingUri')) : $token->access_token;
        } catch (Exception $e) {
            \Log::error($e->getMessage());
            throw new Exception("Failed to connect: " . $e->getMessage());
        }
    }

    public function guzzle(string $type, string $request, array $data = []): array
    {
        try {
            $client = new Client();
            $response = $client->$type(self::$baseUrl . $request, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                    'Prefer' => config('msgraph.preferTimezone'),
                ],
                'body' => json_encode($data),
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Exception $e) {
            \Log::error($e);
            throw new Exception("Failed to execute API request: " . $e->getMessage());
        }
    }

    public function verifySubscriptionAndGetUser(string $clientState, string $tenantId): MsgUserIn
    {
        $this->verifyTenant($tenantId);

        $user = MsgUserIn::where('abn_secret', $clientState)->first();
        if (!$user) {
            throw new Exception("No user found matching the provided client state.");
        }

        return $user;
    }

    public function verifyDraftSubscriptionAndGetUser(string $clientState, string $tenantId): MsgUserDraft
    {
        $this->verifyTenant($tenantId);

        $user = MsgUserDraft::where('abn_secret', $clientState)->first();
        if (!$user) {
            throw new Exception("No user found matching the provided client state.");
        }

        return $user;
    }

    public function verifyTenant(string $tenantId): void
    {
        if ($tenantId !== config('msgraph.tenantId')) {
            throw new Exception("Tenant ID does not match the configured value.");
        }
    }

    public function getAccessToken(bool $returnNullNoAccessToken = false, bool $redirect = false): mixed
    {
        $token = MsgToken::where('user_id', null)->first();

        if (!isset($token->access_token)) {
            if ($returnNullNoAccessToken) {
                return null;
            }
            return $this->connect($redirect);
        }

        if ($token->expires < now()->addMinutes(5)) {
            return $this->connect($redirect);
        }

        return $token->access_token;
    }

    public function getTokenData(): ?MsgToken
    {
        return MsgToken::where('user_id', null)->first();
    }

    protected function storeToken(string $access_token, string $refresh_token, string $expires): MsgToken
    {
        return MsgToken::updateOrCreate(['user_id' => null], [
            'email' => 'application_token',
            'access_token' => $access_token,
            'expires' => time() + $expires,
            'refresh_token' => $refresh_token,
        ]);
    }
}
