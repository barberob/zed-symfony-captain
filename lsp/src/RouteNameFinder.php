<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

use PhpToken;

final class RouteNameFinder
{
    /**
     * @var list<string>
     */
    private const METHODS = ['generate', 'generateUrl', 'redirectToRoute'];

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
        $offset = $this->byteOffsetAt($source, $line, $character);

        foreach ($this->find($source) as $occurrence) {
            if ($offset >= $occurrence->startOffset && $offset < $occurrence->endOffset) {
                return $occurrence;
            }
        }

        return null;
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

    private function byteOffsetAt(string $source, int $line, int $character): int
    {
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length && $line > 0) {
            if ("\n" === $source[$offset]) {
                $line--;
            }

            $offset++;
        }

        $units = 0;

        while ($offset < $length && $units < $character) {
            $units += $this->utf16Units($source[$offset]);
            $offset += $this->utf8Bytes($source[$offset]);
        }

        return $offset;
    }

    private function utf8Bytes(string $firstByte): int
    {
        $byte = ord($firstByte);

        if ($byte < 0x80) {
            return 1;
        }

        if ($byte < 0xE0) {
            return 2;
        }

        if ($byte < 0xF0) {
            return 3;
        }

        return 4;
    }

    private function utf16Units(string $firstByte): int
    {
        return 0xF0 <= ord($firstByte) ? 2 : 1;
    }
}
