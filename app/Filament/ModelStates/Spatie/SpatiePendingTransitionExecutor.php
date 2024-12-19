<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use App\Filament\ModelStates\Contracts\PendingTransition;
use Spatie\ModelStates\Exceptions\ClassDoesNotExtendBaseClass;

final class SpatiePendingTransitionExecutor
{
    public function __construct(
        private readonly SpatieConfig $config,
        private readonly SpatieTransitionTransformer $transitionTransformer,
    ) {}

    /**
     * @throws ClassDoesNotExtendBaseClass
     */
    public function execute(PendingTransition $pendingTransition): void
    {
        $transition = $this->transitionTransformer->transform($pendingTransition);

        $this->config
            ->attributeValue()
            ->transition($transition->unwrap());
    }
}
