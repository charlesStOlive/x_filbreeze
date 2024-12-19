<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use Illuminate\Support\Collection;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\State;
use App\Filament\ModelStates\Contracts\StateSortingStrategy;
use Override;
use Spatie\ModelStates\State as SpatieState;
use Webmozart\Assert\Assert;

final class SpatieConfiguredOrderStateSorting implements StateSortingStrategy
{
    private const TRANSITION_KEY_DIVIDER = '-';

    private SpatieConfig $config;

    /**
     * @param Collection<string, Collection<int, string>> $cache
     */
    public function __construct(
        private readonly Collection $cache = new Collection(),
    ) {}

    #[Override]
    public function withConfig(Config $config): StateSortingStrategy
    {
        $this->config = new SpatieConfig($config);

        return $this;
    }

    #[Override]
    public function compare(State $a, State $b): int
    {
        Assert::isInstanceOf($a, SpatieStateAdapter::class);
        Assert::isInstanceOf($b, SpatieStateAdapter::class);

        $states = $this->getStates();

        return $states->search($a->value()) <=> $states->search($b->value());
    }

    /**
     * @return Collection<int, string>
     */
    private function getStates(): Collection
    {
        $cacheKey = $this->config->stateConfig()->baseStateClass;

        if ($states = $this->cache->get($cacheKey)) {
            return $states;
        }

        return $this->getRegisteredStates()
            ->tap(fn (Collection $states) => $this->cache->put($cacheKey, $states));
    }

    /**
     * @return Collection<int, string>
     */
    private function getRegisteredStates(): Collection
    {
        $stateConfig = $this->config->stateConfig();
        $registeredStates = Collection::make($stateConfig->registeredStates);

        if ($registeredStates->isNotEmpty()) {
            return $registeredStates
                ->map(static fn (string $state): ?string => is_a($state, SpatieState::class, true)
                    ? $state::getMorphClass()
                    : null)
                ->filter(static fn (mixed $state): bool => \is_string($state) && filled($state))
                ->unique()
                ->values();
        }

        $defaultStateClass = $stateConfig->defaultStateClass;

        return Collection::make($stateConfig->allowedTransitions)
            ->keys()
            ->map(static fn (string $transition): array => explode(self::TRANSITION_KEY_DIVIDER, $transition))
            ->flatten(1)
            ->prepend($defaultStateClass ? $defaultStateClass::getMorphClass() : null)
            ->filter(static fn (mixed $state): bool => \is_string($state) && filled($state))
            ->unique()
            ->values();
    }
}
