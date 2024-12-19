<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Concerns;

/**
 * @internal
 */
trait HasAttribute
{
    private string $attribute = 'state';

    public function attribute(string $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    private function getAttribute(): string
    {
        return $this->attribute;
    }
}
