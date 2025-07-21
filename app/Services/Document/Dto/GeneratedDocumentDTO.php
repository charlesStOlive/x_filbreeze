<?php 

namespace App\Services\Document\Dto;

class GeneratedDocumentDTO
{
    public function __construct(
        public string $path,
        public string $name,
        public string $mime,
    ) {}
}