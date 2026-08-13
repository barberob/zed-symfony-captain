<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

use PhpToken;

final class RouteNameFinder extends AbstractRouteReferenceFinder
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
    protected const HALF_TYPED_CALL = '/\b(?:generateUrl|generate|redirectToRoute)\b\s*\(\s*[\'"][^\'"\n]*$/';

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
     * Whether the source is half-typed: tokenization failed, or it ends inside
     * an unterminated string literal (which PHP's tokenizer represents as a
     * trailing `T_ENCAPSED_AND_WHITESPACE` token instead of raising an error).
     * In valid PHP such a token is never the last one.
     */
    protected function isHalfTyped(string $source): bool
    {
        try {
            $tokens = PhpToken::tokenize($source);
        } catch (\ParseError) {
            return true;
        }

        $last = end($tokens);

        return false !== $last && T_ENCAPSED_AND_WHITESPACE === $last->id;
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
