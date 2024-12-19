<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;
use Spatie\ModelStates\Validation\ValidStateRule;

/**
 * @internal
 */
final class SpatieStateValidationRule implements ValidationRule
{
    public function __construct(
        private readonly ValidStateRule $validStateRule,
        private readonly bool $required,
    ) {}

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validStateRule = match ($this->required) {
            true => $this->validStateRule->required(),
            false => $this->validStateRule->nullable(),
        };

        if ($validStateRule->passes($attribute, $value)) {
            return;
        }

        $fail($validStateRule->message());
    }
}
