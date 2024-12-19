<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

use Illuminate\Database\Eloquent\Model;
use Livewire\Wireable;
use Override;
use Spatie\ModelStates\State as SpatieState;

/**
 * @template TModel of Model
 */
interface FilamentSpatieState extends Wireable
{
    /**
     * @return array{model: TModel}
     */
    #[Override]
    public function toLivewire(): array;

    /**
     * @param  array{model: TModel}  $value
     *
     * @return SpatieState<TModel>
     */
    #[Override]
    public static function fromLivewire(mixed $value): SpatieState;
}
