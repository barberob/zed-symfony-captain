<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

/**
 * Builds the LSP `Diagnostic` list for a source file: one warning per dangling
 * route reference — a reference whose name matches no route in the index.
 */
final class RouteDiagnostics
{
    public function __construct(
        private readonly RouteIndex $index,
        private readonly RouteReferenceFinder $finder,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function diagnostics(string $source): array
    {
        $diagnostics = [];

        foreach ($this->finder->find($source) as $occurrence) {
            if (null !== $this->index->findByName($occurrence->name)) {
                continue;
            }

            $diagnostics[] = [
                'range' => [
                    'start' => Position::toPosition($source, $occurrence->startOffset),
                    'end' => Position::toPosition($source, $occurrence->endOffset),
                ],
                'severity' => 2,
                'source' => 'symfony-captain',
                'message' => sprintf("Route '%s' does not exist.", $occurrence->name),
            ];
        }

        return $diagnostics;
    }
}
