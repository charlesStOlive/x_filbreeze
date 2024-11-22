<?php

namespace App\Services\Processors\Emails;

/*
* msgraph api documentation can be found at https://developer.msgraph.com/reference
**/

use Exception;
use App\Models\MsgUserIn;
use App\Models\MsgEmailIn;
use App\Dto\MsGraph\EmailMessageDTO;
use App\Services\Processors\Emails\EmailBaseProcessor;
use App\Contracts\MsGraph\MsGraphEmailServiceInterface;

class EmailPjFactuProcessor extends EmailBaseProcessor implements MsGraphEmailServiceInterface
{
    //STATIC  var comme JSONKEY = e-in-a
    public static function getKey(): string
    {
        return 'e-inpj-f';
    }

    public static function getLabel(): string
    {
        return 'Analyse email Entrant';
    }

    public static function getDescription(): string
    {
        return 'Range dans des dossiers spécifiques les emails entrants';
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
        //Get Service Allias
        $alias = $this->getService('to_adress');
        if(!in_array($alias, $email->toRecipientsMails)) {
            $this->setError('Adresse destinataire non valide : '.$this->email->toRecipentsStringMails);
            return  $this->emailIn;
        }

        if(!$this->email->hasPJs) {
            $this->setError('Pas de PJ');
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
                'to_adress' => [
                    'type' => 'string',
                    'default' => '',
                    'label' => 'Alias',
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
            ],
            'reason' => [
                'type' => 'boolean',
                'default' => 'inc',
            ],
            'nb_facture' => [
                'type' => 'string',
                'default' => '0',
                'label' => 'Nombre de factures traités',
            ],
        ];
    }
}
