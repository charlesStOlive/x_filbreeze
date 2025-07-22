<?php 

namespace App\Services\Pdf\Base;

use Illuminate\Support\Arr;
use App\Services\Pdf\Base\BasePdfTemplate;
use Filament\Pages\BasePage;

class PdfTemplateRegistry
{
    public static function getTemplatesFor(string $modelType): array
    {
        return config("templates-pdf.{$modelType}.templates", []);
    }

    public static function getDefaultTemplateFor(string $modelType): ?string
    {
        return config("templates-pdf.{$modelType}.default");
    }

    public static function getTemplateInstance(string $key, mixed $record): ?BasePdfTemplate
    {
        $modelType = self::resolveModelTypeFromRecord($record);

        $class = collect(self::getTemplatesFor($modelType))
            ->first(fn($cls) => $cls::key() === $key);

        return $class ? new $class($record) : null;
    }

    public static function getDefaultTemplateInstance(mixed $record): BasePdfTemplate
    {
        $modelType = self::resolveModelTypeFromRecord($record);
        $class = self::getDefaultTemplateFor($modelType);

        if (! $class || ! class_exists($class)) {
            throw new \RuntimeException("Aucun template PDF par défaut configuré pour le type '{$modelType}'");
        }

        return new $class($record);
    }

    public static function resolveModelTypeFromRecord(mixed $record): string
    {
        $types = config('templates-pdf.types', []);
        $class = get_class($record);

        return $types[$class]
            ?? throw new \InvalidArgumentException("Aucun type configuré pour le modèle [{$class}].");
    }
}