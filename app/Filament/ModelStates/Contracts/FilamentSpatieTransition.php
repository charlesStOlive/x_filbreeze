<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\Transition as SpatieTransition;

/**
 * @template TModel of Model
 */
interface FilamentSpatieTransition
{
    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $formData
     */
    public static function fill(Model $model, array $formData): SpatieTransition;

    /**
     * @return null|array<\Filament\Schemas\Components\Component>|Closure
     */
    public function form(): array | Closure | null;
}
