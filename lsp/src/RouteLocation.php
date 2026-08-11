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
}
