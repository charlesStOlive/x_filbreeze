<?php 

namespace App\Services\MsGraph\EmailDraft\Base;

interface HasPj
{
    public static function getAvailableAttachments(): array;
}
