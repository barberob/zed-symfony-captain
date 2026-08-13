<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteNameCompleter
{
    private const COMPLETION_ITEM_KIND_CONSTANT = 21;

    public function __construct(
        private readonly RouteIndex $index,
        private readonly RouteReferenceFinder $finder,
    ) {
    }

    /**
     * Builds the LSP completion items for a route name under the cursor, or an
     * empty list when the cursor is not on a route reference. Every route in
     * the index is returned, sorted alphabetically, and the client applies its
     * own fuzzy filtering.
     *
     * @return list<array<string, mixed>>
     */
    public function complete(string $source, int $line, int $character): array
    {
        if (!$this->finder->isAtRouteReference($source, $line, $character)) {
            return [];
        }

        $items = [];

        foreach ($this->index->all() as $entry) {
            $items[] = [
                'label' => $entry->route->name,
                'kind' => self::COMPLETION_ITEM_KIND_CONSTANT,
                'detail' => $entry->route->detail(),
                'documentation' => $entry->route->controller,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);

        return $items;
    }
}
