<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteIndex
{
    /**
     * @param list<RouteEntry> $entries
     */
    public function __construct(
        private readonly array $entries,
    ) {
    }

    /**
     * @return list<RouteEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function findByName(string $name): ?RouteEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->route->name === $name) {
                return $entry;
            }
        }

        return null;
    }
}
