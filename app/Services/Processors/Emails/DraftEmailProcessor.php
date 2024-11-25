<?php

namespace App\Services\Processors\Emails;

/*
* msgraph api documentation can be found at https://developer.msgraph.com/reference
**/


use App\Models\MsgUserIn;
use App\Models\MsgEmailIn;
use App\Models\MsgUserDraft;
use App\Models\MsgEmailDraft;
use App\Dto\MsGraph\EmailMessageDTO;
use App\Services\Processors\Emails\EmailBaseProcessor;
use App\Contracts\MsGraph\MsGraphEmailServiceInterface;

class DraftEmailProcessor extends EmailBaseProcessor
{
    //STATIC  var comme JSONKEY = e-in-a
    
    public static function getKey(): string
    {
        return 'd-cor';
    }

    public static function getLabel(): string
    {
        return 'Corriger le texte';
    }

    public static function getDescription(): string
    {
        return 'Lance une correction sur le texte';
    }

    /**
     * Logique principale pour gérer ce service.
     */
    public function handle(MsgUserDraft $msgUser, EmailMessageDTO $emailData, MsgEmailDraft $email): MsgEmailDraft
    {
        $this->msgUser = $msgUser;
        $this->emailData = $emailData;
        $this->email = $email;
        // Logique pour gérer les données
        if($this->emailData->regexCode !== 'corrige')  {
            $this->setError('Pas de code ou mauvais code : '.$this->emailData->regexCode);
            return  $this->email;
        } else {
            $this->setResult('success', true);
            $this->setResult('code', $this->emailData->regexCode);
            $this->setResult('code_options', $this->emailData->regexCodeOption);
            
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
            'code' => [
                'type' => 'string',
                'default' => 'slug',
                'label' => 'Code de lancemment de la fonction',
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
            'code' => [
                'type' => 'string',
                'default' => 'inc',
                'label' => 'Code identifié',
            ],
            'code_options' => [
                'type' => 'array',
                'default' => [],
                'label' => 'Options',
            ],
            'errors' => [
                'type' => 'array',
                'default' => [],
                'label' => 'Erreurs',
            ],
        ];
    }
    
}
