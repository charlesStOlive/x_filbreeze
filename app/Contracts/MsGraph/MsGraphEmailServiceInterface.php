<?php 

namespace App\Contracts\MsGraph;

use App\Models\MsgUserIn;
use App\Models\MsgEmailIn;
use App\Dto\MsGraph\EmailMessageDTO;

interface MsGraphEmailServiceInterface
{
    public static function getKey(): string;
    public static function getLabel(): string;
    public static function getDescription(): string;
    
    /**
     * Retourne les options du service.
     */
    public static function getServicesOptions(): array;

    /**
     * Logique principale pour gérer le service.
     */
    public function handle(MsgUserIn $data, EmailMessageDTO $email, MsgEmailIn $emailIn): MsgEmailIn;

    /**
     * Retourne les vues spécifiques pour le service.
     */
    public static function getServicesResults(): array;
}
