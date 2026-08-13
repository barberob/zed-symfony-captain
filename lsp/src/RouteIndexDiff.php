<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteIndexDiff
{
    /**
     * Returns the route names present in the previous index but absent from
     * the current one, in the previous index's order. Removing a route is the
     * only index change that can turn existing references into dangling ones.
     *
     * @return list<string>
     */
    public static function removedNames(RouteIndex $previous, RouteIndex $current): array
    {
        $currentNames = [];

        foreach ($current->all() as $entry) {
            $currentNames[$entry->route->name] = true;
        }

        $removed = [];

        foreach ($previous->all() as $entry) {
            if (!isset($currentNames[$entry->route->name])) {
                $removed[] = $entry->route->name;
            }
        }

        return $removed;
    }
}
