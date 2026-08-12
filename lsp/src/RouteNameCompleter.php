<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

use PhpToken;

final class RouteNameCompleter
{
    private const COMPLETION_ITEM_KIND_CONSTANT = 21;

    /**
     * @var string Regex matching a half-typed URL-generation call on a single
     *             line: a recognized method name, `(`, and an opening quote
     *             before the cursor, with only whitespace between.
     */
    private const HALF_TYPED_CALL = '/\b(?:generateUrl|generate|redirectToRoute)\b\s*\(\s*[\'"][^\'"\n]*$/';

    public function __construct(
        private readonly RouteIndex $index,
    ) {
    }

    /**
     * Builds the LSP completion items for a route name under the cursor, or an
     * empty list when the cursor is not on a route reference. Every route in
     * the index is returned, sorted alphabetically, and the client applies its
     * own fuzzy filtering.
     *
     * @return list<array<string, mixed>>
     */
    public function complete(string $source, int $line, int $character): array
    {
        if (!$this->isAtRouteReference($source, $line, $character)) {
            return [];
        }

        $items = [];

        foreach ($this->index->all() as $entry) {
            $items[] = [
                'label' => $entry->route->name,
                'kind' => self::COMPLETION_ITEM_KIND_CONSTANT,
                'detail' => sprintf('%s %s', $entry->route->methodsLabel(), $entry->route->path),
                'documentation' => $entry->route->controller,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);

        return $items;
    }

    /**
     * Whether the cursor sits on a route reference. Clean source is analysed
     * with the same tokenizer logic as `RouteNameFinder`; the lenient
     * line-scoped backward scan only runs when the source is genuinely
     * half-typed (an unterminated string swallows the rest of the file while
     * the user types inside it), so it never fires inside comments or plain
     * strings of a complete file.
     */
    private function isAtRouteReference(string $source, int $line, int $character): bool
    {
        if (null !== (new RouteNameFinder())->findAt($source, $line, $character)) {
            return true;
        }

        return $this->isHalfTyped($source) && $this->isInsideHalfTypedCall($source, $line, $character);
    }

    /**
     * Whether the source is half-typed: tokenization failed, or it ends inside
     * an unterminated string literal (which PHP's tokenizer represents as a
     * trailing `T_ENCAPSED_AND_WHITESPACE` token instead of raising an error).
     * In valid PHP such a token is never the last one.
     */
    private function isHalfTyped(string $source): bool
    {
        try {
            $tokens = PhpToken::tokenize($source);
        } catch (\ParseError) {
            return true;
        }

        $last = end($tokens);

        return false !== $last && T_ENCAPSED_AND_WHITESPACE === $last->id;
    }

    private function isInsideHalfTypedCall(string $source, int $line, int $character): bool
    {
        $offset = Position::toByteOffset($source, $line, $character);
        $lineStart = $offset;

        while ($lineStart > 0 && "\n" !== $source[$lineStart - 1]) {
            $lineStart--;
        }

        $prefix = substr($source, $lineStart, $offset - $lineStart);

        return 1 === preg_match(self::HALF_TYPED_CALL, $prefix);
    }
}
