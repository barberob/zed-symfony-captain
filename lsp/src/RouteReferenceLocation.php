<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteReferenceLocation
{
    public function __construct(
        public readonly string $name,
        public readonly string $file,
        public readonly int $line,
    ) {
    }
}
