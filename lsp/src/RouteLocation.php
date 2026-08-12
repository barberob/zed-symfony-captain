<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteLocation
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
    ) {
    }

    /**
     * Encodes this location as an LSP `Location` (a zero-length range at the
     * start of the method line).
     *
     * @return array<string, mixed>
     */
    public function toLocation(): array
    {
        return [
            'uri' => Uri::fromPath($this->file),
            'range' => [
                'start' => ['line' => $this->line - 1, 'character' => 0],
                'end' => ['line' => $this->line - 1, 'character' => 0],
            ],
        ];
    }
}
