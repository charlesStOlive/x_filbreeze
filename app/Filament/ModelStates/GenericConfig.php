<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use App\Filament\ModelStates\Contracts\Config;
use Override;

final class GenericConfig implements Config
{
    private readonly Model $model;

    private readonly string $attribute;

    public function __construct(
        string | Model $model,
        string $attribute,
    ) {
        $this->model = match (true) {
            \is_string($model) && is_a($model, Model::class, true) => new $model(),
            \is_object($model) && is_a($model, Model::class) => $model,
            default => throw new InvalidArgumentException("The given model [{$model}] should be an Eloquent model."),
        };

        $this->attribute = match (true) {
            \array_key_exists($attribute, $this->model->getAttributes()) => $attribute,
            \array_key_exists($attribute, $this->model->getCasts()) => $attribute,
            default => throw new InvalidArgumentException("The given attribute [{$attribute}] does not exist."),
        };
    }

    #[Override]
    public function model(): Model
    {
        return $this->model;
    }

    #[Override]
    public function attribute(): string
    {
        return $this->attribute;
    }
}
