<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use App\Filament\ModelStates\Contracts\PendingTransition;
use Override;
use Webmozart\Assert\Assert;

final class SpatiePendingTransition implements PendingTransition
{
    public function __construct(
        private readonly PendingTransition $origin,
    ) {}

    #[Override]
    public function from(): SpatieStateAdapter
    {
        $from = $this->origin->from();
        Assert::isInstanceOf($from, SpatieStateAdapter::class);

        return $from;
    }

    #[Override]
    public function to(): SpatieStateAdapter
    {
        $to = $this->origin->to();
        Assert::isInstanceOf($to, SpatieStateAdapter::class);

        return $to;
    }

    #[Override]
    public function formData(): array
    {
        return $this->origin->formData();
    }
}
