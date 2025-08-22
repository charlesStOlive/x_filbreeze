<?php

namespace App\Components\Tree\Concern\Actions;

use App\Components\Tree\Components\Tree;

interface HasTree
{
    public function tree(Tree $tree): static;
}
