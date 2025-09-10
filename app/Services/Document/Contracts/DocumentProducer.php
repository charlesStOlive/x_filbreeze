<?php 

namespace App\Services\Document\Contracts;

use App\Services\Document\Dto\GeneratedDocumentDTO;

interface DocumentProducer
{
    public static function key(): string;
    public static function label(): string;
    public function generateFile(array $options = []): GeneratedDocumentDTO;
    public function download(array $options = []): \Symfony\Component\HttpFoundation\BinaryFileResponse;
}