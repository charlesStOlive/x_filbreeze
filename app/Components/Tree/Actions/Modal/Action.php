<?php

namespace App\Components\Tree\Actions\Modal;

use App\Components\Tree\Concern\Actions\HasTree;
use App\Components\Tree\Concern\BelongsToTree;

/**
 * @deprecated Use `\Filament\Actions\StaticAction` instead.
 */
class Action extends \Filament\Actions\Action implements HasTree
{
    use BelongsToTree;
}
