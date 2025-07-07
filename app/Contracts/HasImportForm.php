<?php 

namespace App\Contracts;

interface HasImportForm
{
    public static function getForm(): array;
    public function finalize(): void;
}
