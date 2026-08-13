<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

/**
 * Detects route references in source files. One implementation exists per
 * language the server understands — the PHP tokenizer-based finder and the
 * Twig lexer-based finder — so features can treat every language alike.
 */
interface RouteReferenceFinder
{
    /**
     * Finds every route name string passed as the first argument to a
     * recognized URL-generation call in the source.
     *
     * @return list<RouteNameOccurrence>
     */
    public function find(string $source): array;

    /**
     * Returns the route name occurrence whose string literal contains the
     * given LSP position (0-based line and character in UTF-16 code units), or
     * null when the cursor is not on a recognized route name string.
     */
    public function findAt(string $source, int $line, int $character): ?RouteNameOccurrence;

    /**
     * Whether the cursor sits on a route reference, including half-typed
     * source the language parser rejects mid-edit.
     */
    public function isAtRouteReference(string $source, int $line, int $character): bool;
}
