<?php

namespace App\Traits\VendorOverrides;

use SolutionForest\FilamentTree\Support\Utils;
use SolutionForest\FilamentTree\Concern\ModelTree as OriginalModelTree;

trait ModelTree
{
    use OriginalModelTree;
    /**
     * Override the `buildSelectArrayItem` method to modify indentation behavior.
     */
    private static function buildSelectArrayItem(array &$final, array $item, string $primaryKeyName, string $titleKeyName, string $childrenKeyName, int $depth, ?int $maxDepth = null): void
    {
        if (!isset($item[$primaryKeyName])) {
            throw new \InvalidArgumentException("Unset '{$primaryKeyName}' primary key.");
        }

        if ($maxDepth && $depth > $maxDepth) {
            return;
        }

        $key = $item[$primaryKeyName];
        $title = $item[$titleKeyName] ?? $item[$primaryKeyName];

        // Customize indentation with Unicode EM SPACE
        $final[$key] = str_repeat("\u{2003}", $depth) . $title; // EM SPACE for indentation

        if (!empty($item[$childrenKeyName])) {
            foreach ($item[$childrenKeyName] as $child) {
                static::buildSelectArrayItem($final, $child, $primaryKeyName, $titleKeyName, $childrenKeyName, $depth + 1, $maxDepth);
            }
        }
    }

    public static function selectArrayNested(?int $maxDepth = null): array
    {
        $result = [];

        $model = app(static::class);

        [$primaryKeyName, $titleKeyName, $parentKeyName, $childrenKeyName] = [
            $model->getKeyName(),
            $model->determineTitleColumnName(),
            $model->determineParentColumnName(),
            static::defaultChildrenKeyName(),
        ];

        $nodes = Utils::buildNestedArray(
            nodes: static::allNodes(),
            parentId: static::defaultParentKey(),
            primaryKeyName: $primaryKeyName,
            parentKeyName: $parentKeyName,
            childrenKeyName: $childrenKeyName
        );

        foreach ($nodes as $node) {
            static::buildSelectArrayItem($result, $node, $primaryKeyName, $titleKeyName, $childrenKeyName, 0, $maxDepth);
        }

        return $result;
    }
}
