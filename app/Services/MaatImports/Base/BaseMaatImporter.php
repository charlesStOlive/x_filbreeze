<?php

namespace App\Services\MaatImports\Base;

use App\Traits\SendsNotifications;
use App\Contracts\HasImportForm;

abstract class BaseMaatImporter implements HasImportForm
{
    use SendsNotifications;

    public array $errors = [];
    public int $created = 0;
    public int $updated = 0;

    protected ?array $options = null;
    protected mixed $record = null;

    public function __construct(?array $options = null, mixed $record = null)
    {
        $this->record = $record;
        $this->options = $options !== null
            ? array_merge(static::getDefaultOptions(), $options)
            : null;
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    public function getMergedOptions(array $runtimeOptions = []): array
    {
        return array_merge(
            static::getDefaultOptions(),
            $this->options ?? [],
            $runtimeOptions
        );
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? static::getDefaultOptions()[$key] ?? $default;
    }

    public static function getDefaultOptions(): array
    {
        return [];
    }

    public function getForm(): array
    {
        return [];
    }

    public function finalize(): void
    {
        if (empty($this->errors)) {
            $this->notifySuccess(
                'Import terminé',
                "✅ {$this->created} créés, {$this->updated} mis à jour"
            );
        } else {
            $message = "❌ {$this->created} créés, {$this->updated} mis à jour, " . count($this->errors) . " erreurs.";
            $this->notifyError('Import partiellement échoué', $message);
        }
    }
}
