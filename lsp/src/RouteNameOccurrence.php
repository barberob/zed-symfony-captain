<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteNameOccurrence
{
    public function __construct(
        public readonly string $name,
        public readonly int $startOffset,
        public readonly int $endOffset,
    ) {
    }
}
