<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Override;
use Spatie\ModelStates\Transition as SpatieTransition;

trait ProvidesSpatieTransitionToFilament
{
    #[Override]
    public static function fill(Model $model, array $formData): SpatieTransition
    {
        $formData = Arr::mapWithKeys(
            $formData,
            static fn (mixed $value, string $key): array => [Str::camel($key) => $value],
        );

        return new self($model, ...$formData);
    }

    #[Override]
    public function form(): array | Closure | null
    {
        return [];
    }
}
