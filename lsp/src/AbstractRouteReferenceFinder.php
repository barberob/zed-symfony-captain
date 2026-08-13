<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

/**
 * Shared cursor behaviour of the per-language route reference finders:
 * locating the occurrence under a cursor and recognising half-typed source
 * with a line-scoped backward scan. The language-specific parsing lives in
 * `find()` and `isHalfTyped()`.
 */
abstract class AbstractRouteReferenceFinder implements RouteReferenceFinder
{
    /**
     * @var string Regex matching a half-typed URL-generation call on a single
     *             line: a recognized function name, `(`, and an opening quote
     *             before the cursor, with only whitespace between.
     */
    protected const HALF_TYPED_CALL = '';

    /**
     * Returns the route name occurrence whose string literal contains the
     * given LSP position (0-based line and character in UTF-16 code units), or
     * null when the cursor is not on a recognized route name string.
     */
    public function findAt(string $source, int $line, int $character): ?RouteNameOccurrence
    {
        $offset = Position::toByteOffset($source, $line, $character);

        foreach ($this->find($source) as $occurrence) {
            if ($offset >= $occurrence->startOffset && $offset < $occurrence->endOffset) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * Whether the cursor sits on a route reference. Clean source is analysed
     * with the same tokenizer logic as `findAt`; the lenient line-scoped
     * backward scan only runs when the source is genuinely half-typed (an
     * unterminated string swallows the rest of the file while the user types
     * inside it), so it never fires inside comments or plain strings of a
     * complete file.
     */
    public function isAtRouteReference(string $source, int $line, int $character): bool
    {
        if (null !== $this->findAt($source, $line, $character)) {
            return true;
        }

        return $this->isHalfTyped($source) && $this->isInsideHalfTypedCall($source, $line, $character);
    }

    /**
     * Whether the source is genuinely half-typed, i.e. the language parser
     * rejects it mid-edit.
     */
    abstract protected function isHalfTyped(string $source): bool;

    private function isInsideHalfTypedCall(string $source, int $line, int $character): bool
    {
        $offset = Position::toByteOffset($source, $line, $character);
        $lineStart = $offset;

        while ($lineStart > 0 && "\n" !== $source[$lineStart - 1]) {
            $lineStart--;
        }

        $prefix = substr($source, $lineStart, $offset - $lineStart);

        return 1 === preg_match(static::HALF_TYPED_CALL, $prefix);
    }
}
