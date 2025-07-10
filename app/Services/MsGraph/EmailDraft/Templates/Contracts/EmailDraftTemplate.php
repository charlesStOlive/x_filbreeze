<?php 

namespace App\Services\MsGraph\EmailDraft\Templates\Contracts;

interface EmailDraftTemplate
{
    public static function key(): string;
    public static function label(): string;

    public function getView(): string;
    public function getData(): array;
    public function getSubject(): string;
}
