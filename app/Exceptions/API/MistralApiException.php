<?php

namespace App\Exceptions\Api;

use App\Exceptions\APIexception;

class MistralApiException extends APIexception
{
    protected $message = 'Erreur lors de l\'appel à l\'API Mistral.';
}
