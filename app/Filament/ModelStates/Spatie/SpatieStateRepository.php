<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Filament\ModelStates\Contracts\State;
use Spatie\ModelStates\State as SpatieState;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
final class SpatieStateRepository
{
    /**
     * @var null|class-string<SpatieState<Model>>
     */
    private ?string $defaultStateClass;

    /**
     * @var Collection<string, class-string<SpatieState<Model>>>
     */
    private Collection $stateClasses;

    public function __construct(
        private readonly SpatieConfig $config,
        private readonly SpatieStateTransformer $stateTransformer,
    ) {
        $defaultStateClass = $config->stateConfig()->defaultStateClass;
        Assert::nullOrIsAOf($defaultStateClass, SpatieState::class);

        $this->defaultStateClass = $defaultStateClass;
        $this->stateClasses = $config->mapping();
    }

    public function current(): State
    {
        return $this->stateTransformer->transform($this->config->attributeValue());
    }

    public function default(): ?State
    {
        return $this->defaultStateClass ? $this->stateTransformer->transform($this->defaultStateClass) : null;
    }

    /**
     * @return Collection<string, State>
     */
    public function all(): Collection
    {
        return $this
            ->stateClasses
            ->map(fn (string $stateClass): State => $this->stateTransformer->transform($stateClass));
    }
}
