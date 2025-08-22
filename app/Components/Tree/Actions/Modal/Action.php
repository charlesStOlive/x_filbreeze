<?php

namespace App\Components\Tree\Actions\Modal;

use Filament\Actions\StaticAction;
use App\Components\Tree\Concern\Actions\HasTree;
use App\Components\Tree\Concern\BelongsToTree;

/**
 * @deprecated Use `\Filament\Actions\StaticAction` instead.
 */
class Action extends StaticAction implements HasTree
{
    use BelongsToTree;
}
