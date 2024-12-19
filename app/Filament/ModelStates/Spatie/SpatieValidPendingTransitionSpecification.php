<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use App\Filament\ModelStates\Contracts\PendingTransition;
use Maartenpaauw\Specifications\CompositeSpecification;
use Override;

/**
 * @extends CompositeSpecification<PendingTransition>
 */
final class SpatieValidPendingTransitionSpecification extends CompositeSpecification
{
    public function __construct(
        private readonly SpatieConfig $config,
    ) {}

    #[Override]
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->config
            ->attributeValue()
            ->canTransitionTo(
                $candidate->to()->value(),
            );
    }
}
