<?php 

namespace App\Exceptions\Processor;

use App\Exceptions\ProcessorException;

class FileEmptyException extends ProcessorException
{
    protected $message = 'Le contenu extrait du fichier est vide.';
}