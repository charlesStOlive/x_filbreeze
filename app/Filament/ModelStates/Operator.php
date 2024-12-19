<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

enum Operator
{
    case In;
    case NotIn;
}
