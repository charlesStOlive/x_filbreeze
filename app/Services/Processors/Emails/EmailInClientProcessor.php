<?php

namespace App\Services\Processors\Emails;

/*
* msgraph api documentation can be found at https://developer.msgraph.com/reference
**/


use App\Models\MsgUserIn;
use App\Models\MsgEmailIn;
use App\Dto\MsGraph\EmailMessageDTO;
use App\Services\Processors\Emails\EmailBaseProcessor;
use App\Contracts\MsGraph\MsGraphEmailServiceInterface;

class EmailInClientProcessor extends EmailBaseProcessor implements MsGraphEmailServiceInterface
{
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
    public function handle(MsgUserIn $msgUser, EmailMessageDTO $email, MsgEmailIn $emailIn): MsgEmailIn
    {
        $this->msgUser = $msgUser;
        $this->email = $email;
        $this->emailIn = $emailIn;
        // Logique pour gérer les données
        if(!in_array($this->email->toRecipientsMails, ['factu@notilac.fr'])) {
            $this->setError('Adresse email non valide');
            return  $this->emailIn;
        }
        
        
        //Mettre a jours ces valeurs via le cast. 
        return  $this->emailIn;
    }




    /**
     * Retourne les options du service.
     */
    public static function getServicesOptions(): array
    {
        return [
            'mode' => [
                'type' => 'list',
                'default' => 'inactive',
                'label' => 'Mode',
                'values' => [
                    'inactive' => 'Inactif',
                    'active' => 'Actif',
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
