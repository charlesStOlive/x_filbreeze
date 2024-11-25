<?php

namespace App\Services\MsGraph;

/*
* msgraph api documenation can be found at https://developer.msgraph.com/reference
**/

use Arr;
use Exception;
use GuzzleHttp\Client;
use App\Models\MsgUserIn;
use App\Models\MsgUserDraft;
use App\Models\MsgToken;
use App\Models\MsgEmailIn;
use App\Models\MsgEmailDraft;
use App\Dto\MsGraph\EmailMessageDTO;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use App\Services\Processors\Emails\EmailPjFactuProcessor;
use App\Services\Processors\Emails\EmailInClientProcessor;

class MsgConnect
{
    protected static string $baseUrl = 'https://graph.microsoft.com/v1.0/';

    public function isConnected(): bool
    {
        $token = $this->getTokenData();

        if ($token === null) {
            return false;
        }

        if ($token->expires < time()) {
            return false;
        }

        return true;
    }

    public function connect(bool $redirect = true): mixed
    {

        $params = [
            'scope' => 'https://graph.microsoft.com/.default',
            'client_id' => config('msgraph.clientId'),
            'client_secret' => config('msgraph.clientSecret'),
            'grant_type' => 'client_credentials',
        ];
        $token = null;

        try {
            $client = new Client;
            $response = $client->post(config('msgraph.tenantUrlAccessToken'), ['form_params' => $params]);
            $token =  json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            return json_decode(($e->getResponse()->getBody()->getContents()));
        } catch (Exception $e) {
            \Log::error($e->getMessage());
            throw new Exception($e->getMessage());
        }

        if (isset($token->access_token)) {
            $this->storeToken($token->access_token, '', $token->expires_in);
        }

        if ($redirect) {
            return redirect(config('msgraph.msgraphLandingUri'));
        }
        return $token->access_token ?? null;
    }

    public function getUsers()
    {
        $users = $this->guzzle('get', 'users');
        return $users;
    }

    public function subscribeToDraftNotifications(string $userId, string $secretClientValue): array
    {
        $expirationDate = now()->addHours(24);

        try {
            $subscription = [
                'changeType' => 'created,updated', // Types de modifications à surveiller
                'notificationUrl' => url('/api/email-draft-notifications'), // Nouvelle route pour les brouillons
                'resource' => "users/{$userId}/mailFolders('Drafts')/messages", // Dossier des brouillons
                'expirationDateTime' => $expirationDate->toISOString(), // Date d'expiration de l'abonnement
                'clientState' => $secretClientValue, // Clé de sécurité pour vérifier les notifications
            ];

            $response = $this->guzzle('post', 'subscriptions', $subscription);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            \Log::error('Failed to subscribe to draft notifications: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to subscribe to draft notifications'];
        }
    }

    public function unsubscribeFromDraftNotifications(string $subscriptionId): array
    {
        try {
            $response = $this->guzzle('delete', 'subscriptions/' . $subscriptionId);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            \Log::error('Failed to unsubscribe from draft notifications: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to unsubscribe from draft notifications'];
        }
    }

    public function renewDraftNotificationSubscription(string $subscriptionId): array
    {
        $expirationDate = now()->addHours(26);

        try {
            $subscription = [
                'expirationDateTime' => $expirationDate->toISOString(),
            ];
            $response = $this->guzzle('patch', 'subscriptions/' . $subscriptionId, $subscription);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            \Log::error('Failed to renew draft notification subscription: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to renew draft notification subscription'];
        }
    }




    public function subscribeToEmailNotifications(string $userId, string $secretClientValue): array
    {
        $expirationDate = now()->addHours(24);

        try {
            $subscription = [
                'changeType' => 'created', // ou 'updated,deleted' selon les besoins
                'notificationUrl' =>  url('/api/email-notifications'), // Votre endpoint qui traitera les notifications
                'resource' => 'users/' . $userId . '/mailFolders(\'Inbox\')/messages', // Chemin de la ressource à surveiller
                'expirationDateTime' => $expirationDate->toISOString(), // Date d'expiration de l'abonnement
                'clientState' => $secretClientValue,
            ];

            $response = $this->guzzle('post', 'subscriptions', $subscription);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            \Log::error('Failed to subscribe to email notifications: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to subscribe to email notifications'];
        }
    }

    public function unsubscribeFromEmailNotifications(string $subscriptionId): array
    {
        try {
            $response = $this->guzzle('delete', 'subscriptions/' . $subscriptionId);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            \Log::error('Failed to unsubscribe from email notifications: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to unsubscribe from email notifications'];
        }
    }

    public function renewEmailNotificationSubscription(string $subscriptionId): array
    {
        $expirationDate = now()->addHours(26);
        try {
            $subscription = [
                'expirationDateTime' => $expirationDate->toISOString(),
            ];
            $response = $this->guzzle('patch', 'subscriptions/' . $subscriptionId, $subscription);
            return ['success' => true, 'response' => $response];
        } catch (Exception $e) {
            //\Log::error('Failed to renew email notification subscription: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to renew email notification subscription'];
        }
    }

    public function processEmailNotification($notificationData)
    {
        $data = $notificationData['value'][0];
        $clientState = $data['clientState'];
        $tenantId = $data['tenantId'];
        $messageId = $data['resourceData']['id'];

        try {
            $user = $this->verifySubscriptionAndgetUser($clientState, $tenantId);
        } catch (\Exception $e) {
            \Log::error("Error in subscription verification: " . $e->getMessage());
            throw $e; // Propagate the exception
        }
        try {
            $emailData = $this->guzzle('get', "users/{$user->ms_id}/messages/{$messageId}");
            $this->launchSuscribedServices($user, $emailData);
        } catch (Exception $e) {
            \Log::error($e);
            throw $e;
        }
        return;
    }

    public function processDraftNotification($notificationData)
    {
        $data = $notificationData['value'][0];
        $clientState = $data['clientState'];
        $tenantId = $data['tenantId'];
        $messageId = $data['resourceData']['id'];

        try {
            // Vérifier l'abonnement et récupérer l'utilisateur
            $user = $this->verifyDraftSubscriptionAndgetUser($clientState, $tenantId);
        } catch (\Exception $e) {
            \Log::error("Error in draft subscription verification: " . $e->getMessage());
            throw $e; // Propagation de l'exception
        }

        try {
            // Correction de l'URL pour récupérer le brouillon
            $emailData = $this->guzzle('get', "users/{$user->ms_id}/mailFolders('Drafts')/messages/{$messageId}");
            \Log::info("Draft email data:", $emailData);
            $emailDTO = EmailMessageDTO::fromArray($emailData);
            \Log::info($emailDTO->bodyOriginal);
        } catch (Exception $e) {
            \Log::error("Failed to fetch draft email: " . $e->getMessage());
            throw $e;
        }

        return;
    }


    public function launchTestServices(MsgUserIn $user, $emailData)
    {
        $this->launchSuscribedServices($user, $emailData);
    }


    public function launchSuscribedServices(MsgUserIn $user, array $emailData)
    {
        $newEmailIn = $user->msg_email_ins()->make();
        $emailDTO = EmailMessageDTO::fromArray($emailData);
        $newEmailIn->services = $user->services;
        $newEmailIn->from = $emailDTO->fromEmail;
        $newEmailIn->subject = $emailDTO->subject;
        $newEmailIn->tos = $emailDTO->allRecipentsStringMails;
        //Appelle des deux classes avec la methode Handle
        if ($newEmailIn->{'services.e-in-a.mode'} === 'actif') { //Retrouver la valeur actif ou non pour cette class dans le json services qui a et copié dans le mailIn.
            $emailInClient = new EmailInClientProcessor();
            $newEmailIn = $emailInClient->handle($user, $emailDTO, $newEmailIn);
        }

        if ($newEmailIn->{'services.e-inpj-f.mode'}  === 'actif') { //Retrouver la valeur active ou non pour cette class
            $emailPjFactu = new EmailPjFactuProcessor();
            $newEmailIn = $emailPjFactu->handle($user, $emailDTO, $newEmailIn);
        }
        $newEmailIn->save();
    }

    protected function verifyDraftSubscriptionAndgetUser($clientState, $tenantId)
    {
        if ($tenantId != config('msgraph.tenantId')) {
            //\Log::info('Différence entre msgraph.tenantId et tenantId: '.config('msgraph.tenantId'));
            throw new \Exception("Tenant ID does not match the configured value.");
        }
        // Suppose that MsgUser is your Eloquent model and it has `mds_id` and `abn_secret` fields
        $user = MsgUserDraft::where('abn_secret', $clientState)->first();
        if (!$user) {
            throw new \Exception("No user found matching the provided client state.");
        }
        return $user;
    }

    protected function verifySubscriptionAndgetUser($clientState, $tenantId)
    {
        if ($tenantId != config('msgraph.tenantId')) {
            //\Log::info('Différence entre msgraph.tenantId et tenantId: '.config('msgraph.tenantId'));
            throw new \Exception("Tenant ID does not match the configured value.");
        }
        // Suppose that MsgUser is your Eloquent model and it has `mds_id` and `abn_secret` fields
        $user = MsgUserIn::where('abn_secret', $clientState)->first();
        if (!$user) {
            throw new \Exception("No user found matching the provided client state.");
        }
        return $user;
    }

    public function forwardEmail($user, $emailIn, $messageId, $forwardedTo, $comment)
    {
        try {
            $comment = sprintf('## %s ## ', $emailIn->from);
            $forwardData = [
                'message' => [
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => $emailIn->forwarded_to,
                            ],
                        ],
                    ],
                ],
                'comment' => $comment,
                'saveToSentItems' => true,
            ];
            // \Log::info('forwardData');
            // \Log::info($forwardData);
            $forwardResult = $this->guzzle('post', "users/{$user->ms_id}/messages/{$messageId}/forward", $forwardData);
            // \Log::info("forwardResult");
            // \Log::info($forwardResult);

            return $forwardResult;
        } catch (Exception $e) {
            \Log::error("Failed to forward email: " . $e->getMessage());
            throw new Exception('Failed to forward email. Please try again later.');
        }
    }

    public function setEmailIsRead($user, $messageId, $isRead = true)
    {
        try {
            $updateData = [
                'isRead' => $isRead,
            ];
            return $this->guzzle('patch', "users/{$user->ms_id}/messages/{$messageId}", $updateData);
        } catch (Exception $e) {
            //\Log::error("Failed to move email to folder: " . $e->getMessage());
            throw new Exception('Failed to set Is Read. Please try again later.');
        }
    }

    public function setEmailInFOlder($user, $emailIn, $messageId, $folderName)
    {
        try {
            $existingFolder = $this->guzzle('get', "users/{$user->ms_id}/mailFolders?\$filter=displayName eq '{$folderName}'");
            if (count($existingFolder['value']) > 0) {
                // Utiliser l'ID du dossier existant
                $folderId = $existingFolder['value'][0]['id'];
            } else {
                // Créer un nouveau dossier nommé comme spécifié dans $emailIn->move_to_folder
                $folderData = [
                    'displayName' => $folderName,
                ];
                $folderResponse = $this->guzzle('post', "users/{$user->ms_id}/mailFolders", $folderData);
                $folderId = $folderResponse['id'];
            }

            // Déplacer l'email dans le dossier spécifié
            $moveData = [
                'destinationId' => $folderId,
            ];
            $folderResult =  $this->guzzle('post', "users/{$user->ms_id}/messages/{$messageId}/move", $moveData);
            return  $folderResult;
        } catch (Exception $e) {
            //\Log::error("Failed to move email to folder: " . $e->getMessage());
            throw new Exception('Failed to move email to folder. Please try again later.');
        }
    }

    public function updateEmail($user, $messageId, array $updateData)
    {
        try {
            // $updateData = [
            //     'subject' => $emailIn->new_subject,
            //     'categories' => [$emailIn->category],
            //     'body' => [
            //         'contentType' => $emailIn->contentType,
            //         'content' => $emailIn->body
            //     ],
            // ];

            // Update the email
            $this->guzzle('patch', "users/{$user->ms_id}/messages/{$messageId}", $updateData);
            return true;
        } catch (Exception $e) {
            \Log::error("Failed to update email: " . $e->getMessage());
            throw new Exception('Failed to update email. Please try again later.');
        }
    }

    public function updateDraftEmail($user, $messageId, array $updateData)
    {
        try {
            // $updateData = [
            //     'subject' => $emailIn->new_subject,
            //     'categories' => [$emailIn->category],
            //     'body' => [
            //         'contentType' => $emailIn->contentType,
            //         'content' => $emailIn->body
            //     ],
            // ];

            // Update the email
            $this->guzzle('patch', "users/{$user->ms_id}/messages/{$messageId}", $updateData);
            return true;
        } catch (Exception $e) {
            \Log::error("Failed to update email: " . $e->getMessage());
            throw new Exception('Failed to update email. Please try again later.');
        }
    }

    public function getAccessToken(bool $returnNullNoAccessToken = false, bool $redirect = false): mixed
    {
        // Admin token will be stored without user_id
        $token = MsgToken::where('user_id', null)->first();

        // Check if tokens exist otherwise run the oauth request
        if (!isset($token->access_token)) {
            // Don't request new token, simply return null when no token found with this option
            if ($returnNullNoAccessToken) {
                return null;
            }

            return $this->connect($redirect);
        }

        $now = now()->addMinutes(5);

        if ($token->expires < $now) {
            return $this->connect($redirect);
        } else {

            // Token is still valid, just return it
            return $token->access_token;
        }
    }

    public function getTokenData(): MsgToken|null
    {
        return MsgToken::where('user_id', null)->first();
    }

    protected function storeToken(string $access_token, string $refresh_token, string $expires): MsgToken
    {
        //Create or update a new record for admin token
        return MsgToken::updateOrCreate(['user_id' => null], [
            'email' => 'application_token', // Placeholder name
            'access_token' => $access_token,
            'expires' => (time() + $expires),
            'refresh_token' => $refresh_token,
        ]);
    }


    /**
     * @throws Exception
     */
    protected function guzzle(string $type, string $request, array $data = []): array
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

            $responseObject = json_decode($response->getBody()->getContents(), true);
            return $responseObject ?? [];
        } catch (ClientException $e) {
            //\Log::error("HTTP request failed: " . $e->getMessage());
            return json_decode($e->getResponse()->getBody()->getContents(), true) ?? ['error' => 'Failed to process request'];
        } catch (Exception $e) {
            //\Log::error("Unexpected error: " . $e->getMessage());
            throw new Exception('Internal server error. Please try again later.');
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function getPagination(array $data, string $top = '0', string $skip = '0'): array
    {
        $total = $data['@odata.count'] ?? 0;

        if (isset($data['@odata.nextLink'])) {
            $parts = parse_url($data['@odata.nextLink']);
            parse_str($parts['query'], $query);

            $top = $query['$top'] ?? 0;
            $skip = $query['$skip'] ?? 0;
        }

        return [
            'total' => $total,
            'top' => $top,
            'skip' => $skip,
        ];
    }
}
