<?php

namespace App\Services\Processors\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MsgUserDraft;
use App\Models\MsgEmailDraft;
use App\Dto\MsGraph\EmailMessageDTO;

class DraftEmailProcessor  implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use EmailProcessorTrait; // Importation du trait

    public MsgUserDraft $msgUser;
    public EmailMessageDTO $emailData;
    public MsgEmailDraft $email;

    /**
     * Constructeur pour initialiser les propriétés.
     */
    public function __construct(MsgUserDraft $msgUser, EmailMessageDTO $emailData, MsgEmailDraft $email)
    {
        $this->msgUser = $msgUser;
        $this->emailData = $emailData;
        $this->email = $email;
    }

    /**
     * Clé du service.
     */
    public static function getKey(): string
    {
        return 'd-cor';
    }

    /**
     * Label du service.
     */
    public static function getLabel(): string
    {
        return 'Corriger le texte';
    }

    /**
     * Description du service.
     */
    public static function getDescription(): string
    {
        return 'Lance une correction sur le texte';
    }

    /**
     * Vérifie si la classe doit être exécutée.
     */
    public function shouldResolve(): bool
    {
        // Exemple de logique pour déterminer si l'exécution est requise
        if ($this->emailData->regexCode !== 'corrige') {
            $this->setError('Pas de code ou mauvais code : ' . $this->emailData->regexCode);
            //$this->email->save(); necessaire ? 
            return true;
        } else {
            $this->setResult('success', true);
            $this->setResult('code', $this->emailData->regexCode);
            $this->setResult('code_options', $this->emailData->regexCodeOption);
            //$this->email->save(); necessaire ? 
            return false;
        }
    }

    /**
     * Logique principale pour traiter les données directement.
     */
    public function resolve(): MsgEmailDraft
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
    public static function onQueue(MsgUserDraft $msgUser, EmailMessageDTO $emailData, MsgEmailDraft $email)
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
            'code' => [
                'type' => 'string',
                'default' => 'slug',
                'label' => 'Code de lancement de la fonction',
            ],
        ];
    }

    /**
     * Retourne les résultats spécifiques pour ce service.
     */
    public static function getServicesResults(): array
    {
        return [
            'success' => [
                'type' => 'boolean',
                'default' => false,
                'label' => 'Email Traité',
                'hidden' => true,
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
