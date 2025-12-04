<?php

namespace App\Services\MsGraph\EmailDraft\Base;

use function config;
use RuntimeException;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use App\Services\MsGraph\EmailDraft\Base\BaseDraftEmailTemplate;



class EmailDraftTemplateRegistry
{
    public static function getTemplatesFor(string $modelType): array
    {
        return config("templates-email-draft.{$modelType}.templates", []);
    }

    public static function getDefaultTemplateFor(string $modelType): ?string
    {
        return config("templates-email-draft.{$modelType}.default");
    }

    public static function getTemplateInstance(string $key, mixed $record, ?array $options = null): ?BaseDraftEmailTemplate
    {
        $modelType = self::resolveModelTypeFromRecord($record);

        $class = collect(self::getTemplatesFor($modelType))
            ->first(fn($cls) => $cls::key() === $key);

        return $class ? new $class($record, $options) : null;
    }

    public static function getDefaultTemplateInstance(mixed $record, ?array $options = null): BaseDraftEmailTemplate
    {
        $modelType = self::resolveModelTypeFromRecord($record);
        $class = self::getDefaultTemplateFor($modelType);

        if (! $class || ! class_exists($class)) {
            throw new RuntimeException("Aucun template email par d�faut configur� pour le type '{$modelType}'");
        }

        return new $class($record, $options);
    }

    public static function resolveModelTypeFromRecord(mixed $record): string
    {
        $types = config('templates-email-draft.types', []);
        $class = get_class($record);
        \Log::info($types);


        return $types[$class]
            ?? throw new InvalidArgumentException("Aucun type configur� pour le mod�le [{$class}].");
    }
}