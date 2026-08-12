<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class WorkspaceSymbols
{
    private const SYMBOL_KIND_FUNCTION = 12;

    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * Builds the LSP workspace symbols for the given route index: one symbol
     * per route, named `Route: <name>` with detail `<METHOD> <path>`. Routes
     * with a resolvable controller point to the controller file and method
     * line. The other routes are still returned, with a fallback location so
     * the response stays parseable by strict LSP clients.
     *
     * @return list<array<string, mixed>>
     */
    public function build(RouteIndex $index): array
    {
        $symbols = [];

        foreach ($index->all() as $entry) {
            $symbols[] = [
                'name' => sprintf('Route: %s', $entry->route->name),
                'kind' => self::SYMBOL_KIND_FUNCTION,
                'detail' => $entry->route->detail(),
                'location' => $this->location($entry),
            ];
        }

        return $symbols;
    }

    /**
     * @return array<string, mixed>
     */
    private function location(RouteEntry $entry): array
    {
        if (null !== $entry->location) {
            return $entry->location->toLocation();
        }

        return (new RouteLocation($this->projectRoot . '/bin/console', 1))->toLocation();
    }
}
