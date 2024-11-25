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

class EmailInClientProcessor extends EmailBaseProcessor
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
    public function handle(MsgUserIn $msgUser, EmailMessageDTO $emailData, MsgEmailIn $email): MsgEmailIn
    {
        $this->msgUser = $msgUser;
        $this->emailData = $emailData;
        $this->email = $email;
        // Logique pour gérer les données
        if(!in_array($this->emailData->toRecipientsMails, ['factu@notilac.fr'])) {
            $this->setError('Adresse email non valide');
            return  $this->email;
        }
        
        
        //Mettre a jours ces valeurs via le cast. 
        return  $this->email;
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
