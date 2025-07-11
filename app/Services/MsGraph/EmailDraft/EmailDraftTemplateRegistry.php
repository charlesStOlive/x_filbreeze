<?php

namespace App\Services\MsGraph\EmailDraft;

use Illuminate\Support\Arr;
use App\Services\MsGraph\EmailDraft\Templates\Contracts\EmailDraftTemplate;

class EmailDraftTemplateRegistry
{
    public static function getTemplatesFor(string $modelType): array
    {
        return config("email-draft-templates.{$modelType}.templates", []);
    }

    public static function getDefaultTemplateFor(string $modelType): ?string
    {
        return config("email-draft-templates.{$modelType}.default");
    }

    public static function getTemplateInstance(string $key, mixed $record): ?EmailDraftTemplate
    {
        $modelType = self::resolveModelTypeFromRecord($record);

        $class = collect(self::getTemplatesFor($modelType))
            ->first(fn($cls) => $cls::key() === $key);

        return $class ? new $class($record) : null;
    }

    public static function getDefaultTemplateInstance(mixed $record): EmailDraftTemplate
    {
        $modelType = self::resolveModelTypeFromRecord($record);
        $class = self::getDefaultTemplateFor($modelType);

        if (! $class || ! class_exists($class)) {
            throw new \RuntimeException("Aucun template par défaut configuré pour le type '{$modelType}'");
        }

        return new $class($record);
    }

    public static function resolveModelTypeFromRecord(mixed $record): string
    {
        $types = config('email-draft-templates.types', []);
        $class = get_class($record);

        return $types[$class]
            ?? throw new \InvalidArgumentException("Aucun type configuré pour le modèle [{$class}].");
    }
}
