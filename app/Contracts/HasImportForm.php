<?php 

namespace App\Contracts;

interface HasImportForm
{
    public function getForm(): array;
    public function finalize(): void;
}
