<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

use Closure;
use Filament\Forms\Components\Component;
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
     * @return null|array<Component>|Closure
     */
    public function form(): array | Closure | null;
}
