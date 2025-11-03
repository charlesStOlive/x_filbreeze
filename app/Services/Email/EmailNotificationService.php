<?php

namespace App\Services\Email;

use App\Models\MsgUserIn;
use App\Models\MsgEmailIn;
use App\Models\MsgUserDraft;
use App\Models\MsgEmailDraft;
use App\Services\Email\Dto\EmailMessageDTO;
use App\Services\Email\Contracts\EmailClient;
use App\Services\Email\Services\EmailStatusCalculator;
use App\Infrastructure\MsGraph\Mappers\GraphMessageMapper;
use App\Infrastructure\MsGraph\GraphAuthService;
use App\Services\Processors\Emails\DraftEmailProcessor;
use App\Services\Processors\Emails\TradEmailProcessor;
use App\Services\Processors\Emails\EmailPjFactuProcessor;
use App\Services\Processors\Emails\EmailInClientProcessor;


class EmailNotificationService
{
    protected GraphAuthService $authService;
    protected EmailClient $emailClient;
    protected GraphMessageMapper $mapper;
    protected EmailStatusCalculator $statusCalculator;

    public function __construct(
        GraphAuthService $authService,
        EmailClient $emailClient,
        GraphMessageMapper $mapper,
        EmailStatusCalculator $statusCalculator
    ) {
        $this->authService = $authService;
        $this->emailClient = $emailClient;
        $this->mapper = $mapper;
        $this->statusCalculator = $statusCalculator;
    }

    public function processEmailNotification(array $notificationData)
    {
        $data = $notificationData['value'][0];
        $clientState = $data['clientState'];
        $tenantId = $data['tenantId'];
        $messageId = $data['resourceData']['id'];

        $user = $this->authService->verifySubscriptionAndGetUser($clientState, $tenantId);
        $rawEmailData = $this->emailClient->fetchEmail($user, $messageId);
        $emailDTO = $this->mapper->toDomain($rawEmailData);

        $this->launchSubscribedServices($user, $emailDTO);
    }

    public function processDraftNotification(array $notificationData)
    {
        $data = $notificationData['value'][0];
        $clientState = $data['clientState'];
        $tenantId = $data['tenantId'];
        $messageId = $data['resourceData']['id'];

        $user = $this->authService->verifyDraftSubscriptionAndGetUser($clientState, $tenantId);
        $rawEmailData = $this->emailClient->fetchDraft($user, $messageId);
        $emailDTO = $this->mapper->toDomain($rawEmailData);
        $this->launchSubscribedDraftServices($user, $emailDTO);
    }

    public function launchSubscribedServices(MsgUserIn $user, EmailMessageDTO $emailDTO)
    {
        $newEmailIn = $user->msg_email_ins()->make()->fill($emailDTO->basicEmailData());
        $newEmailIn->services_options = $user->services_options;

        // Charger dynamiquement les services depuis la configuration
        $emailInProcessors = config('msgraph.email-in', []);

        foreach ($emailInProcessors as $processorClass) {
            if (class_exists($processorClass)) {
                // Obtenir la clé du service via la méthode statique
                $serviceKey = $processorClass::getKey();

                // Vérifier si le service est actif ou en test
                $mode = data_get($newEmailIn->services_options, $serviceKey . '.mode');
                if (in_array($mode, ['actif', 'test'])) {
                    $emailInClient = new $processorClass($user, $emailDTO, $newEmailIn);
                    // Backwards-compatible gating: prefer preflight() when implemented
                    // (newer processors use preflight/perform). Fall back to shouldResolve()
                    // for older processors that still implement the old trait.
                    if (method_exists($emailInClient, 'preflight')) {
                        try {
                            $pre = $emailInClient->preflight();
                            if ($pre && $pre->proceed) {
                                // Vérifier si le processeur supporte la queue
                                if (method_exists($processorClass, 'supportsQueue') && !$processorClass::supportsQueue()) {
                                    // Processeur preflight-only, traitement terminé
                                    \Log::info("Processor {$serviceKey} completed in preflight (no queue support)");
                                } elseif (method_exists($processorClass, 'onQueue')) {
                                    // Processeur classique avec queue
                                    $processorClass::onQueue($user, $emailDTO, $newEmailIn);
                                }
                            } else {
                                \Log::info("Processor {$serviceKey} blocked in preflight: " . ($pre?->reason ?? 'no reason'));
                            }
                        } catch (\Exception $e) {
                            \Log::error("Processor {$serviceKey} preflight threw: " . $e->getMessage());
                        }
                    } elseif (method_exists($emailInClient, 'shouldResolve') && $emailInClient->shouldResolve()) {
                        if (method_exists($processorClass, 'onQueue')) {
                            $processorClass::onQueue($user, $emailDTO, $newEmailIn);
                        }
                    }
                }
            }
        }

        // Recalculer le statut global après tous les preflight
        $this->recomputeEmailStatus($newEmailIn);
        $newEmailIn->save();
    }

    public function launchSubscribedDraftServices(MsgUserDraft $user, EmailMessageDTO $emailDTO)
    {
        $newEmailDraft = $user->msg_email_drafts()->make()->fill($emailDTO->basicEmailData());
        $newEmailDraft->services_options = $user->services_options;

        // Charger dynamiquement les services depuis la configuration
        $draftProcessors = config('msgraph.email-draft', []);

        foreach ($draftProcessors as $processorClass) {
            if (!class_exists($processorClass)) continue;

            $serviceKey = $processorClass::getKey();
            $mode = data_get($newEmailDraft->services_options, $serviceKey . '.mode');

            if (!in_array($mode, ['actif', 'test'])) continue;

            /** @var \App\Services\Processors\Emails\BaseEmailDraftProcessor $processor */
            $processor = new $processorClass($user, $emailDTO, $newEmailDraft);

            try {
                $pre = $processor->preflight(); // Phase 1 synchrone (valide + beginProcessor + "Je travaille")

                if ($pre->proceed) {
                    // Vérifier si le processeur supporte la queue
                    if (method_exists($processorClass, 'supportsQueue') && !$processorClass::supportsQueue()) {
                        // Processeur preflight-only, traitement terminé
                        \Log::info("Processor {$serviceKey} completed in preflight (no queue support)");
                    } else {
                        // Processeur classique avec queue
                        $processorClass::onQueue($user, $emailDTO, $newEmailDraft); // Phase 2 queue
                    }
                } else {
                    \Log::info("Processor {$serviceKey} bloqué: " . $pre->reason);
                }
            } catch (\Exception $e) {
                \Log::error("Processor {$serviceKey} preflight threw: " . $e->getMessage());
            }
        }

        // Recalculer le statut global après tous les preflight
        $this->recomputeEmailStatus($newEmailDraft);
        $newEmailDraft->save();
    }

    /**
     * Recalcule le statut global de l'email après les preflight
     */
    protected function recomputeEmailStatus($email): void
    {
        $newStatus = $this->statusCalculator->compute(
            $email->active_jobs ?? 0,
            $email->has_error ?? false,
            $email->services_results ?? []
        );

        $email->status = $newStatus;

        if ($newStatus === 'end') {
            $email->finished_at = now();
        }
    }
}
