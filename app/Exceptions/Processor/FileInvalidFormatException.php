<?php 

namespace App\Exceptions\Processor;

use App\Exceptions\ProcessorException;

class FileInvalidFormatException extends ProcessorException
{
    protected $message = 'Le fichier  n\'est pas dans un format valide. (image ou pdf attendu)';
}