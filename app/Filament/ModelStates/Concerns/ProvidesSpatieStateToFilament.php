<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Concerns;

use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State as SpatieState;
use Webmozart\Assert\Assert;

trait ProvidesSpatieStateToFilament
{
    #[Override]
    public function toLivewire(): array
    {
        return [
            'model' => $this->getModel(),
        ];
    }

    /**
     * @throws InvalidConfig
     */
    #[Override]
    public static function fromLivewire(mixed $value): SpatieState
    {
        Assert::isArrayAccessible($value);
        Assert::keyExists($value, 'model');

        $model = $value['model'];
        Assert::isInstanceOf($model, Model::class);

        return SpatieState::make(static::class, $model);
    }
}
