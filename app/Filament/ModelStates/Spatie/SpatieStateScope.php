<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Arr;
use App\Filament\ModelStates\Contracts\State;
use App\Filament\ModelStates\Operator;
use Override;

/**
 * @internal
 *
 * @template TModel of Model
 */
final class SpatieStateScope implements Scope
{
    /**
     * @param  array<array-key, State>|State  $states
     */
    public function __construct(
        private readonly State | array $states,
        private readonly Operator $operator,
        private readonly SpatieConfig $config,
    ) {}

    /**
     * @param  Builder<TModel>  $builder
     */
    #[Override]
    public function apply(Builder $builder, Model $model): void
    {
        $states = Arr::map(
            Arr::wrap($this->states),
            static fn (State $state): string => $state->value(),
        );

        $attribute = $this->config->model()->qualifyColumn($this->config->attribute());

        match ($this->operator) {
            Operator::In => $builder->whereIn($attribute, $states),
            Operator::NotIn => $builder->whereNotIn($attribute, $states),
        };
    }
}
