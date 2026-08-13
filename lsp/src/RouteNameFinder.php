<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

use PhpToken;

final class RouteNameFinder implements RouteReferenceFinder
{
    /**
     * @var list<string>
     */
    private const METHODS = ['generate', 'generateUrl', 'redirectToRoute'];

    /**
     * @var string Regex matching a half-typed URL-generation call on a single
     *             line: a recognized method name, `(`, and an opening quote
     *             before the cursor, with only whitespace between.
     */
    private const HALF_TYPED_CALL = '/\b(?:generateUrl|generate|redirectToRoute)\b\s*\(\s*[\'"][^\'"\n]*$/';

    /**
     * Detects every route name string passed as the first argument to a call
     * to a recognized Symfony URL-generation method. Parsing uses PHP's native
     * tokenizer, so route-like strings elsewhere in the source are ignored.
     *
     * @return list<RouteNameOccurrence>
     */
    public function find(string $source): array
    {
        try {
            $tokens = PhpToken::tokenize($source);
        } catch (\ParseError) {
            return [];
        }

        $occurrences = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (T_STRING !== $token->id || !in_array($token->text, self::METHODS, true)) {
                continue;
            }

            $openParen = $this->nextSignificantToken($tokens, $i + 1);

            if (null === $openParen || '(' !== $tokens[$openParen]->text) {
                continue;
            }

            $firstArgument = $this->nextSignificantToken($tokens, $openParen + 1);

            if (null === $firstArgument || T_CONSTANT_ENCAPSED_STRING !== $tokens[$firstArgument]->id) {
                continue;
            }

            $string = $tokens[$firstArgument];

            $occurrences[] = new RouteNameOccurrence(
                name: $this->stringValue($string->text),
                startOffset: $string->pos,
                endOffset: $string->pos + strlen($string->text),
            );
        }

        return $occurrences;
    }

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

    /**
     * @param list<PhpToken> $tokens
     */
    private function nextSignificantToken(array $tokens, int $from): ?int
    {
        $count = count($tokens);

        for ($i = $from; $i < $count; $i++) {
            $token = $tokens[$i];

            if (T_WHITESPACE === $token->id || T_COMMENT === $token->id || T_DOC_COMMENT === $token->id) {
                continue;
            }

            return $i;
        }

        return null;
    }

    private function stringValue(string $text): string
    {
        if (str_starts_with($text, "'")) {
            $value = substr($text, 1, -1);

            return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        }

        $value = substr($text, 1, -1);

        return str_replace(['\\\\', '\\"', '\\$'], ['\\', '"', '$'], $value);
    }
}
