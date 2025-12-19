<?php

namespace App\Support\Prism;

use Carbon\Carbon;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;
use ReflectionClass;
use ReflectionNamedType;

trait HasPrismSchema
{
    /**
     * Description par champ (à surcharger dans le DTO).
     * @return array<string, string>
     */
    protected static function prismFieldDescriptions(): array
    {
        return [];
    }

    /**
     * Champs requis supplémentaires (à surcharger si tu veux forcer des champs).
     * Exemple: voir plus bas.
     * @return string[]
     */
    protected static function prismRequiredOverrides(): array
    {
        return [];
    }

    /**
     * Champs à exclure du schema (rare, mais utile).
     * @return string[]
     */
    protected static function prismExcludedFields(): array
    {
        return [];
    }

    public static function prismSchema(
        ?string $name = null,
        ?string $description = null
    ): ObjectSchema {
        $ref = new ReflectionClass(static::class);
        $ctor = $ref->getConstructor();

        $descriptions = static::prismFieldDescriptions();
        $excluded = array_flip(static::prismExcludedFields());

        $properties = [];
        $required = [];

        foreach ($ctor?->getParameters() ?? [] as $param) {
            $field = $param->getName();

            if (isset($excluded[$field])) {
                continue;
            }

            $type = $param->getType();
            $typeName = null;
            $nullable = true;

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $nullable = $type->allowsNull();
            }

            $fieldDescription = $descriptions[$field] ?? self::fallbackDescription($field);

            // ⚠️ Important : Carbon/DateTime => string côté IA (ton cast Spatie fera le boulot ensuite)
            $schema = match ($typeName) {
                'string' => new StringSchema($field, $fieldDescription, nullable: $nullable),

                'int', 'float' => new NumberSchema($field, $fieldDescription, nullable: $nullable),

                Carbon::class, \DateTimeInterface::class => new StringSchema(
                    $field,
                    $fieldDescription . ' (format attendu: YYYY-MM-DD si possible)',
                    nullable: $nullable
                ),

                default => new StringSchema(
                    $field,
                    $fieldDescription . ' (texte)',
                    nullable: true // fallback prudent
                ),
            };

            $properties[] = $schema;

            // requiredFields "cohérents" :
            // - non nullable
            // - et pas de valeur par défaut
            if ($type instanceof ReflectionNamedType) {
                $isRequired = !$nullable && !$param->isDefaultValueAvailable();
                if ($isRequired) {
                    $required[] = $field;
                }
            }
        }

        // overrides (pour forcer certains champs requis même s'ils sont ?string)
        $required = array_values(array_unique(array_merge($required, static::prismRequiredOverrides())));

        return new ObjectSchema(
            name: $name ?? self::defaultSchemaName(static::class),
            description: $description ?? 'Structured output schema',
            properties: $properties,
            requiredFields: $required
        );
    }

    private static function defaultSchemaName(string $fqcn): string
    {
        $short = class_basename($fqcn);

        // supplier_invoice_extraction_dto -> supplier_invoice_extraction
        $short = preg_replace('/DTO$/', '', $short);

        // CamelCase -> snake_case
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

        return $snake;
    }

    private static function fallbackDescription(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
