<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Actions\Exports\ExportColumn;
use App\Filament\ModelStates\Concerns\DisplaysState;

final class StateExportColumn extends ExportColumn
{
    use DisplaysState;
}
