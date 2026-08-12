<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class Route
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public readonly string $name,
        public readonly array $methods,
        public readonly string $path,
        public readonly string $controller,
    ) {
    }

    /**
     * The HTTP methods as a pipe separated label, or `ANY` when the route
     * accepts every method.
     */
    public function methodsLabel(): string
    {
        return [] === $this->methods ? 'ANY' : implode('|', $this->methods);
    }
}
