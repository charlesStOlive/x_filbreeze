<?php

namespace App\Services\MsGraph\EmailDraft\Base;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\CheckboxList;

abstract class BaseDraftEmailTemplate
{
    protected ?array $options = null;

    public function __construct(?array $options = null)
    {
        $this->options = $options !== null
            ? array_merge(static::getDefaultOptions(), $options)
            : null;
    }

    abstract public static function key(): string;

    abstract public static function label(): string;

    abstract public function getView(): string;

    abstract public function getSubject(array $options = []): string;

    abstract protected function getRecord(): mixed;

    abstract public function getData(array $options = []): array;

    public static function getDefaultOptions(): array
    {
        return [];
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

    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    public function hasPj(): bool
    {
        return $this instanceof HasPj;
    }

    public static function hasPjStatic(): bool
    {
        return in_array(HasPj::class, class_implements(static::class));
    }

    public function getAttachmentForm(): ?Group
    {
        if (! $this->hasPj()) {
            return null;
        }

        return Group::make([
            CheckboxList::make('attachments')
                ->label('Pièces jointes')
                ->options(static::getAttachmentOptions())
        ]);
    }

    public static function getAttachmentOptions(): array
    {
        if (! static::hasPjStatic()) return [];

        return collect(static::getAvailableAttachments())
            ->mapWithKeys(fn($default, $cls) => [$cls::key() => $cls::label()])
            ->toArray();
    }

    public static function getDefaultAttachments(): array
    {
        if (! static::hasPjStatic()) return [];

        return collect(static::getAvailableAttachments())
            ->filter(fn($default) => $default === true)
            ->keys()
            ->map(fn($cls) => $cls::key())
            ->toArray();
    }

    public function generateAttachments(array $options = [], array $selected = []): array
    {
        if (! $this->hasPj()) return [];

        $record = $this->getRecord();

        return collect(static::getAvailableAttachments())
            ->filter(fn($default, $cls) => in_array($cls::key(), $selected))
            ->map(fn($default, $cls) => (new $cls($record))->generateFile(
                $this->getMergedOptions($options)
            ))
            ->values()
            ->all();
    }
}

