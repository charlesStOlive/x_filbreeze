<?php

namespace App\Services\Processors\Emails;

use App\Models\MsgUserDraft;
use App\Models\MsgEmailDraft;
use App\Dto\MsGraph\EmailMessageDTO;
use App\Services\MsGraph\MsGraphEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TradEmailProcessor  implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use EmailProcessorTrait; // Importation du trait

    protected MsGraphEmailService $emailService;
    protected MsgUserDraft $user;
    protected EmailMessageDTO $emailData;
    protected MsgEmailDraft $email;
    /**
     * Constructeur pour initialiser les propriétés.
     */
    public function __construct(MsgUserDraft $user, EmailMessageDTO $emailData, MsgEmailDraft $email, MsGraphEmailService|null $emailService = null)
    {
        $this->emailService = $emailService ? $emailService : $this->resolveEmailService();
        $this->user = $user;
        $this->emailData = $emailData;
        $this->email = $email;
    }

    /**
     * Clé du service.
     */
    public static function getKey(): string
    {
        return 'd-trad';
    }

    /**
     * Label du service.
     */
    public static function getLabel(): string
    {
        return 'Traduire  le mail';
    }

    /**
     * Description du service.
     */
    public static function getDescription(): string
    {
        return 'Traduit le mail de la langue source vers le français et inversement';
    }

    /**
     * Vérifie si la classe doit être exécutée.
     */
    public function shouldResolve(): bool
    {
        // Exemple de logique pour déterminer si l'exécution est requise
        if ($this->emailData->regexCode !== 'traduit') {
            $this->setError('Pas de code ou mauvais code : ' . $this->emailData->regexCode);
            //$this->email->save(); necessaire ? 
            return false;
        } else {
            $this->setResult('success', true);
            $this->setResult('code', $this->emailData->regexCode);
            $this->setResult('code_options', $this->emailData->regexCodeOption);
            return $this->launchStartingState();
        }
    }

    /**
     * Logique principale pour traiter les données directement.
     */
    public function resolve(): MsgEmailDraft
    {
        // Logique principale
        \Log::info('Resolve---------');
        $options = $this->getResult('code_options');
        $update = false;
        if($options['u'] ?? false) {
            unset($options['u']);
            $update = true;
        }
        $langKeyConfig = array_key_first($options);
        $lang = 'en';
        if($langKeyConfig) {
            $lang = 'fr';
        }
        if(!$update) {
            $newEmailData = clone $this->emailData;
            $newEmailData->bodyOriginal = $this->removeRegexKeyAndLineIfEmptyHTML($newEmailData->bodyOriginal);
            //On ajoute le code langue au debut du texte : 
            $newEmailData->bodyOriginal = sprintf('[%s]%s', $lang, $newEmailData->bodyOriginal);
            \Log::info("body original");
            \Log::info($newEmailData->bodyOriginal);
            $newEmailData->bodyOriginal = $this->callMistralAgent($newEmailData->bodyOriginal);
            $responseN = $this->emailService->createDraft($this->user, $newEmailData->getDataForNewEmail());
            \Log::info("reponse de mistral");
            \Log::info($responseN);
            $newBody = $this->emailData->bodyOriginal = $this->insertInRegexKey('Terminé');;
            $responseD = $this->emailService->updateEmail($this->user, $this->email, [
                'body' => ['contentType' => $this->emailData->contentType, 'content' => $this->emailData->bodyOriginal],
            ]);
        } else {
            $this->emailData->bodyOriginal = $this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyOriginal);
            $this->emailData->bodyOriginal = sprintf('[%s]%s', $lang, $this->emailData->bodyOriginal);
            $this->emailData->bodyOriginal = $this->callMistralAgent($this->emailData->bodyOriginal);
            $this->emailService->updateEmail($this->user, $this->email, [
                'body' => ['contentType' => $this->emailData->contentType, 'content' => $this->emailData->bodyOriginal],
            ]);

        }
        
        sleep(1);
        $this->email->status = 'end';
        // A venir
        return $this->email;
    }

    private function callMistralAgent(string $mistralPrompt): string
    {
        $mistralAgent = new \App\Services\Ia\MistralAgentService(); // Instanciation directe
        $agentId = 'ag:3e2c948d:20241128:untitled-agent:863e968f';
        $response = $mistralAgent->callAgent($agentId, $mistralPrompt);
        \Log::info('MISTRAL RESPONSE');
        \Log::info($response['choices'][0]['message']['content'] ?? '');
        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Méthode appelée automatiquement lorsqu'elle est mise en file d'attente.
     */
    public function handle()
    {
        \Log::info('----Lancement du handle----');
        $this->resolve()->save();
        \Log::info('----Fin du handle----');
    }

    /**
     * Méthode statique pour lancer la queue après vérification.
     */
    public static function onQueue(MsgUserDraft $user, EmailMessageDTO $emailData, MsgEmailDraft $email)
    {
        \Log::info('lancement de la queue');
        try {
            $processor = new self($user, $emailData, $email);
            dispatch($processor);
        } catch(\Exception $ex) {
            \Log::info($ex->getMessage());
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
