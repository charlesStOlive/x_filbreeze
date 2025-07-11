<?php 

namespace App\Services\MsGraph\EmailDraft\Templates\Contracts;

interface EmailDraftTemplate
{
    public static function key(): string;
    public static function label(): string;

    public function getView(): string;
    public function getSubject(): string;

    public function getData(array $options = []): array;

    public static function getForm(array $defaults = []): array;

    public static function getDefaultOptions(): array;
}