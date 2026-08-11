<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteEntry
{
    public function __construct(
        public readonly Route $route,
        public readonly ?RouteLocation $location,
    ) {
    }
}
