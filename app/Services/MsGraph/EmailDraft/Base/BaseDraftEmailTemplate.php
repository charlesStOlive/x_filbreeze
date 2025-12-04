<?php

namespace App\Services\MsGraph\EmailDraft\Base;

use Filament\Schemas\Components\Group;
use Filament\Forms\Components\CheckboxList;

abstract class BaseDraftEmailTemplate
{
    protected mixed $record;
    protected ?array $options = null;

    public function __construct(mixed $record, ?array $options = null)
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

    abstract public static function key(): string;

    abstract public static function label(): string;

    abstract public function getView(): string;

    abstract public function getToOptions(): array;

    abstract public function getDefaultTo(): array;

    abstract public function getSubject(array $options = []): string;

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
        return array_key_exists($key, $this->options ?? []);
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
        if (! $this->hasPj()) return null;

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

        return collect(static::getAvailableAttachments())
            ->filter(fn($default, $cls) => in_array($cls::key(), $selected))
            ->map(fn($default, $cls) => (new $cls($this->record, $this->getMergedOptions($options)))->generateFile())
            ->values()
            ->all();
    }
}
