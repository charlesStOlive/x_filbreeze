<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use App\Filament\ModelStates\Contracts\PendingTransition;
use App\Filament\ModelStates\Contracts\State;
use Override;

final class GenericPendingTransition implements PendingTransition
{
    /**
     * @param  array<string, mixed>  $formData
     */
    public function __construct(
        private readonly State $from,
        private readonly State $to,
        private readonly array $formData = [],
    ) {}

    #[Override]
    public function from(): State
    {
        return $this->from;
    }

    #[Override]
    public function to(): State
    {
        return $this->to;
    }

    #[Override]
    public function formData(): array
    {
        return $this->formData;
    }
}
