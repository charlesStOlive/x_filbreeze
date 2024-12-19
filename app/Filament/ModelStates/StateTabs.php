<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Closure;
use Filament\Resources\Components\Tab;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Filament\ModelStates\Concerns\HasDriver;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\State;
use Override;

/**
 * @implements Arrayable<string, Tab>
 */
final class StateTabs implements Arrayable
{
    use EvaluatesClosures;
    use HasDriver;

    private Closure | bool $includeAllTab = true;

    public function __construct(Config $config)
    {
        $this->stateConfig($config);
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function make(string $model, string $attribute = 'state'): self
    {
        return new self(new GenericConfig($model, $attribute));
    }

    public function includeAllTab(Closure | bool $include = true): self
    {
        $this->includeAllTab = $include;

        return $this;
    }

    #[Override]
    public function toArray(): array
    {
        return $this->getStateDriver()
            ->allStates($this->getStateConfig())
            ->map(function (State $state): Tab {
                return Tab::make($state->value())
                    ->label(static fn (): string => $state->label())
                    ->icon(static fn (): ?string => $state->getIcon())
                    ->query(function (Builder $query) use ($state): Builder {
                        $scope = $this->getStateDriver()
                            ->scope($this->getStateConfig(), $state, Operator::In);

                        return $query->withGlobalScope($scope::class, $scope);
                    });
            })
            ->when(
                $this->evaluate($this->includeAllTab),
                static fn (Collection $tabs): Collection => $tabs->prepend(
                    Tab::make(__('model-states-for-filament::labels.all')),
                    'all',
                ),
            )
            ->all();
    }
}
