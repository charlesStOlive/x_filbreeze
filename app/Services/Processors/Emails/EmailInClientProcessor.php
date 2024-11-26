<?php

namespace App\Services\Processors\Emails;

/*
* msgraph api documentation can be found at https://developer.msgraph.com/reference
**/


use App\Models\MsgUserIn;
use App\Models\MsgEmailIn;
use App\Dto\MsGraph\EmailMessageDTO;
use App\Services\MsGraph\MsGraphEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class EmailInClientProcessor  implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use EmailProcessorTrait;
    //
    protected MsGraphEmailService $emailService;
    public MsgUserIn $msgUser;
    public EmailMessageDTO $emailData;
    public MsgEmailIn $email;

    public function __construct(MsgUserIn $msgUser, EmailMessageDTO $emailData, MsgEmailIn $email,  MsGraphEmailService|null $emailService = null)
    {
        $this->emailService = $emailService ? $emailService : $this->resolveEmailService();
        $this->msgUser = $msgUser;
        $this->emailData = $emailData;
        $this->email = $email;
    }
    //STATIC  var comme JSONKEY = e-in-a
    
    public static function getKey(): string
    {
        return 'e-in-a';
    }

    public static function getLabel(): string
    {
        return 'Ranger email dans dossier client';
    }

    public static function getDescription(): string
    {
        return 'Règles d’acceptation des emails...';
    }

    /**
     * Logique principale pour gérer ce service.
     */
    public function shouldResolve(): bool
    {
        // Logique pour gérer les données
        if(!in_array($this->emailData->toRecipientsMails, ['factu@notilac.fr'])) {
            $this->setError('Adresse email non valide');
            return  false;
        }
        //Mettre a jours ces valeurs via le cast. 
        $this->setResult('success', true);
        return  true;
    }

    /**
     * Logique principale pour traiter les données directement.
     */
    public function resolve(): MsgEmailIn
    {
        // Logique principale
        // A venir
        return $this->email;
    }

    /**
     * Méthode appelée automatiquement lorsqu'elle est mise en file d'attente.
     */
    public function handle()
    {
        $this->resolve()->save();
    }

    /**
     * Méthode statique pour lancer la queue après vérification.
     */
    public static function onQueue(MsgUserIn $msgUser, EmailMessageDTO $emailData, MsgEmailIn $email)
    {
        $processor = new self($msgUser, $emailData, $email);
        if ($processor->shouldResolve()) {
            dispatch($processor);
        }
    }
    /**
     * Retourne les options du service.
     */
    public static function getServicesOptions(): array
    {
        return [
            'mode' => [
                'type' => 'list',
                'default' => 'inactif',
                'label' => 'Mode',
                'values' => [
                    'inactif' => 'Inactif',
                    'actif' => 'Actif',
                    'test' => 'Test',
                ],
            ],
            'field' => [
                'type' => 'string',
                'default' => 'slug',
                'label' => 'Champs client pour dossier',
            ],
        ];
    }

    /**
     * Retourne les vues spécifiques pour ce service.
     */
    public static function getServicesResults(): array
    {
        return [
            'success' => [
                'type' => 'boolean',
                'default' => false,
                'label' => 'Email Traité',
            ],
            'reason' => [
                'type' => 'boolean',
                'default' => 'inc',
                'label' => 'Raison',
            ],
            'newfolder' => [
                'type' => 'boolean',
                'default' => '',
                'label' => 'Nouveau dossier',
            ],
        ];
    }
    
}
