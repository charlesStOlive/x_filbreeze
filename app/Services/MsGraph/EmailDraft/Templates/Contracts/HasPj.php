<?php 

namespace App\Services\MsGraph\EmailDraft\Templates\Contracts;

interface HasPj
{
    public static function getAvailableAttachments(): array;
}
