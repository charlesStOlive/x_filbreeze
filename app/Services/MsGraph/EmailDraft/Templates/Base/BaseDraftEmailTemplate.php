<?php

namespace App\Services\MsGraph\EmailDraft\Templates\Base;

use Filament\Forms\Components\Group;

use Filament\Forms\Components\CheckboxList;
use App\Services\MsGraph\EmailDraft\Templates\Contracts\HasPj;

abstract class BaseDraftEmailTemplate
{
    abstract public function getView(): string;

    abstract public function getData(array $options = []): array;

    abstract public function getSubject(array $options = []): string;

    abstract protected function getRecord(): mixed;

    public function hasPj(): bool
    {
        return $this instanceof HasPj;
    }

    public static function hasPjStatic(): bool
    {
        return in_array(
            \App\Services\MsGraph\EmailDraft\Templates\Contracts\HasPj::class,
            class_implements(static::class)
        );
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

        $pjs = collect(static::getAvailableAttachments())
            ->mapWithKeys(fn($default, $cls) => [$cls::key() => $cls::label()])
            ->toArray();

        \Log::info('available attachments', $pjs);

        return $pjs;
    }

    public static function getDefaultAttachments(): array
    {
        if (! static::hasPjStatic()) return [];

        $default = collect(static::getAvailableAttachments())
            ->filter(fn($default) => $default === true)
            ->keys()
            ->map(fn($cls) => $cls::key())
            ->toArray();

        \Log::info('default attachments', $default);

        return $default;
    }

    public function generateAttachments(array $options = [], array $selected = []): array
    {
        if (! $this->hasPj()) return [];

        $record = $this->getRecord();

        return collect(static::getAvailableAttachments())
            ->filter(fn($default, $cls) => in_array($cls::key(), $selected))
            ->map(fn($default, $cls) => (new $cls($record))->generateFile($options))
            ->values()
            ->all();
    }
}
