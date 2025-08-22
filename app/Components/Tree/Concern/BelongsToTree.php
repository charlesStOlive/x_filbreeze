<?php

namespace App\Components\Tree\Concern;

use App\Components\Tree\Components\Tree;
use App\Components\Tree\Contract\HasTree;

trait BelongsToTree
{
    protected Tree $tree;

    public function tree(Tree $tree): static
    {
        $this->tree = $tree;

        return $this;
    }

    public function getTree(): Tree
    {
        return $this->tree;
    }

    public function getLivewire(): HasTree
    {
        return $this->getTree()->getLivewire();
    }
}
