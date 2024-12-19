<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use App\Filament\ModelStates\Contracts\Config;
use Override;
use Spatie\ModelStates\State as SpatieState;
use Spatie\ModelStates\StateConfig;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
final class SpatieConfig implements Config
{
    public function __construct(
        private readonly Config $origin,
    ) {}

    #[Override]
    public function model(): Model
    {
        return $this->origin->model();
    }

    #[Override]
    public function attribute(): string
    {
        return $this->origin->attribute();
    }

    /**
     * @return SpatieState<Model>
     */
    public function attributeValue(): SpatieState
    {
        $attributeValue = $this->model()->getAttribute($this->attribute());
        Assert::isInstanceOf($attributeValue, SpatieState::class);

        return $attributeValue;
    }

    public function stateConfig(): StateConfig
    {
        return $this->stateCast()::config();
    }

    /**
     * @return Collection<string, class-string<SpatieState<Model>>>
     */
    public function mapping(): Collection
    {
        return $this->stateCast()::getStateMapping();
    }

    /**
     * @return class-string<SpatieState<Model>>
     */
    private function stateCast(): string
    {
        $stateCast = Arr::get($this->model()->getCasts(), $this->attribute());
        Assert::string($stateCast);
        Assert::isAOf($stateCast, SpatieState::class);

        return $stateCast;
    }
}
