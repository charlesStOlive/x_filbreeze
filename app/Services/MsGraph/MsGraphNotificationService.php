<?php 

namespace App\Services\MsGraph;

use App\Dto\MsGraph\EmailMessageDTO;
use App\Models\MsgEmailDraft;
use App\Models\MsgEmailIn;
use App\Models\MsgUserDraft;
use App\Models\MsgUserIn;
use App\Services\Processors\Emails\EmailPjFactuProcessor;
use App\Services\Processors\Emails\EmailInClientProcessor;
use App\Services\Processors\Emails\DraftEmailProcessor;


class MsGraphNotificationService
{
    protected MsGraphAuthService $authService;
    protected MsGraphEmailService $emailService;

    public function __construct(
        MsGraphAuthService $authService,
        MsGraphEmailService $emailService
    ) {
        $this->authService = $authService;
        $this->emailService = $emailService;
    }

    public function processEmailNotification(array $notificationData)
    {
        $data = $notificationData['value'][0];
        $clientState = $data['clientState'];
        $tenantId = $data['tenantId'];
        $messageId = $data['resourceData']['id'];

        $user = $this->authService->verifySubscriptionAndGetUser($clientState, $tenantId);
        $emailData = $this->emailService->fetchEmailData($user, new MsgEmailIn(['email_id' => $messageId]));

        $this->launchSubscribedServices($user, $emailData);
    }

    public function processDraftNotification(array $notificationData)
    {
        \Log::info('--processDraftNotification--');
        \Log::info($notificationData);
        $data = $notificationData['value'][0];
        $clientState = $data['clientState'];
        $tenantId = $data['tenantId'];
        $messageId = $data['resourceData']['id'];

        \Log::info($clientState);

        $user = $this->authService->verifyDraftSubscriptionAndGetUser($clientState, $tenantId);
        $emailData = $this->emailService->fetchEmailData($user, new MsgEmailDraft(['email_id' => $messageId]));

        $this->launchSubscribedDraftServices($user, $emailData);
    }

    public function launchSubscribedServices(MsgUserIn $user, array $emailData)
    {
        $emailDTO = EmailMessageDTO::fromArray($emailData);

        $newEmailIn = $user->msg_email_ins()->make()->fill($emailDTO->basicEmailData());
        $newEmailIn->services_options = $user->services_options;

        if ($newEmailIn->{'services.e-in-a.mode'} === 'actif') {
            $emailInClient = new EmailInClientProcessor($user, $emailDTO, $newEmailIn);
            
        }

        if ($newEmailIn->{'services.e-inpj-f.mode'} === 'actif') {
            $emailPjFactu = new EmailPjFactuProcessor($user, $emailDTO, $newEmailIn);
            
        }

        $newEmailIn->save();
    }

    public function launchSubscribedDraftServices(MsgUserDraft $user, array $emailData)
    {
        $emailDTO = EmailMessageDTO::fromArray($emailData);

        $existingEmail = MsgEmailDraft::where('email_id', $emailDTO->id)
            ->whereNotIn('status', ['end', 'error'])
            ->first();

        if ($existingEmail) {
            return;
        }

        $newEmailDraft = $user->msg_email_drafts()->make()->fill($emailDTO->basicEmailData());
        $newEmailDraft->services_options = $user->services_options;

        if ($newEmailDraft->{'services_options.d-cor.mode'} === 'actif') {
            $emailDraftClient = new DraftEmailProcessor($user, $emailDTO, $newEmailDraft);
            if ($emailDraftClient->shouldResolve()) {
                $emailDraftClient->resolve();
            }
        }

        $newEmailDraft->save();
    }
}
