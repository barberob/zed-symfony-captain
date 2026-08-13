<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\Token;

/**
 * Detects route references in Twig templates with Twig's own lexer, so only
 * genuine `path()`/`url()` function calls are matched. Twig tokens carry no
 * byte offsets, so a byte cursor is tracked as the token stream is walked.
 */
final class TwigRouteNameFinder extends AbstractRouteReferenceFinder
{
    /**
     * @var list<string>
     */
    private const METHODS = ['path', 'url'];

    /**
     * @var string Regex matching a half-typed URL-generation call on a single
     *             line: a `path`/`url` call not preceded by a `.` (attribute
     *             access) or `|` (filter), an open paren, and an opening quote
     *             before the cursor, with only whitespace between.
     */
    protected const HALF_TYPED_CALL = '/(?<![.|])\b(?:path|url)\s*\(\s*[\'"][^\'"\n]*$/';

    /**
     * Twig's own number literal regex, used to reconstruct the raw span of
     * `NUMBER` tokens whose value is cast to a numeric type.
     */
    private const REGEX_NUMBER = '/(?(DEFINE)
        (?<LNUM>[0-9]+(_[0-9]+)*)
        (?<FRAC>\.(?&LNUM))
        (?<EXPONENT>[eE][+-]?(?&LNUM))
        (?<DNUM>(?&LNUM)(?:(?&FRAC))?)
    )(?:(?&DNUM)(?:(?&EXPONENT))?)
    /Ax';

    private ?Environment $environment;

    public function __construct(?Environment $environment = null)
    {
        $this->environment = $environment;
    }

    /**
     * Detects every route name string passed as the first argument to a
     * `path()` or `url()` call in the template, in output tags and logic
     * blocks alike. Parsing uses Twig's own lexer, so route-like strings
     * elsewhere in the template are ignored.
     *
     * @return list<RouteNameOccurrence>
     */
    public function find(string $source): array
    {
        try {
            $tokens = $this->tokens($source);
        } catch (SyntaxError) {
            return [];
        }

        $offsets = $this->byteOffsets($source, $tokens);
        $count = count($tokens);
        $occurrences = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (Token::NAME_TYPE !== $token->getType() || !in_array($token->getValue(), self::METHODS, true)) {
                continue;
            }

            if ($i > 0 && in_array($this->valueOf($tokens[$i - 1]), ['.', '|'], true)) {
                continue;
            }

            if ('(' !== $this->valueOf($tokens[$i + 1] ?? null)) {
                continue;
            }

            $stringToken = $tokens[$i + 2] ?? null;

            if (null === $stringToken || Token::STRING_TYPE !== $stringToken->getType()) {
                continue;
            }

            $occurrences[] = new RouteNameOccurrence(
                name: $stringToken->getValue(),
                startOffset: $offsets[$i + 2][0],
                endOffset: $offsets[$i + 2][1],
            );
        }

        return $occurrences;
    }

    /**
     * @return list<Token>
     */
    private function tokens(string $source): array
    {
        $environment = $this->environment();

        if (null === $environment) {
            return [];
        }

        $stream = $environment->tokenize(new Source($source, 'index'));
        $tokens = [];

        while (!$stream->isEOF()) {
            $tokens[] = $stream->getCurrent();
            $stream->next();
        }

        $tokens[] = $stream->getCurrent();

        return $tokens;
    }

    private function environment(): ?Environment
    {
        if (null === $this->environment) {
            if (!class_exists(Environment::class)) {
                return null;
            }

            $this->environment = new Environment(new ArrayLoader());
        }

        return $this->environment;
    }

    /**
     * Reconstructs the byte range of every token by walking the source with a
     * byte cursor, since Twig tokens only expose their line number.
     *
     * @param list<Token> $tokens
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function byteOffsets(string $source, array $tokens): array
    {
        $length = strlen($source);
        $cursor = 0;
        $offsets = [];

        foreach ($tokens as $index => $token) {
            if (Token::TEXT_TYPE === $token->getType()) {
                $start = $cursor;
                $cursor = $this->afterText($source, $cursor, $length);
                $offsets[$index] = [$start, $cursor];

                continue;
            }

            $this->skipTrivia($source, $cursor, $length);
            $start = $cursor;

            $cursor = match ($token->getType()) {
                Token::VAR_START_TYPE, Token::BLOCK_START_TYPE => $this->afterTagStart($source, $cursor, $length),
                Token::VAR_END_TYPE, Token::BLOCK_END_TYPE => $this->afterTagEnd($source, $cursor, $length),
                Token::STRING_TYPE => $this->afterString($source, $cursor, $length),
                Token::NUMBER_TYPE => $this->afterNumber($source, $cursor, $length),
                Token::INTERPOLATION_START_TYPE => $cursor + 2,
                Token::INTERPOLATION_END_TYPE => $cursor + 1,
                default => $cursor + strlen((string) $token->getValue()),
            };

            $offsets[$index] = [$start, $cursor];
        }

        return $offsets;
    }

    /**
     * Moves the cursor past a text region, which runs until the next Twig
     * opening delimiter (`{{`, `{%`, or `{#`) and past any trailing comment.
     */
    private function afterText(string $source, int $cursor, int $length): int
    {
        $cursor = $this->nextTagStart($source, $cursor, $length);

        return '{#' === substr($source, $cursor, 2) ? $this->afterComment($source, $cursor, $length) : $cursor;
    }

    private function nextTagStart(string $source, int $from, int $length): int
    {
        $position = $length;

        foreach (['{{', '{%', '{#'] as $marker) {
            $found = strpos($source, $marker, $from);

            if (false !== $found && $found < $position) {
                $position = $found;
            }
        }

        return $position;
    }

    private function afterComment(string $source, int $cursor, int $length): int
    {
        $end = strpos($source, '#}', $cursor + 2);

        return false === $end ? $length : $end + 2;
    }

    /**
     * Moves the cursor past whitespace and comments that separate tokens inside
     * and between tags.
     */
    private function skipTrivia(string $source, int &$cursor, int $length): void
    {
        while ($cursor < $length) {
            if (str_contains(" \t\r\n", $source[$cursor])) {
                $cursor++;

                continue;
            }

            if ('{#' === substr($source, $cursor, 2)) {
                $cursor = $this->afterComment($source, $cursor, $length);

                continue;
            }

            return;
        }
    }

    private function afterTagStart(string $source, int $cursor, int $length): int
    {
        $cursor += 2;

        if ($cursor < $length && ('-' === $source[$cursor] || '~' === $source[$cursor])) {
            $cursor++;
        }

        return $cursor;
    }

    private function afterTagEnd(string $source, int $cursor, int $length): int
    {
        if ($cursor < $length && ('-' === $source[$cursor] || '~' === $source[$cursor])) {
            $cursor++;
        }

        return $cursor + 2;
    }

    /**
     * Moves the cursor past a string literal, honouring backslash escapes.
     */
    private function afterString(string $source, int $cursor, int $length): int
    {
        $quote = $source[$cursor];
        $cursor++;

        while ($cursor < $length) {
            if ('\\' === $source[$cursor]) {
                $cursor += 2;

                continue;
            }

            if ($quote === $source[$cursor]) {
                return $cursor + 1;
            }

            $cursor++;
        }

        return $length;
    }

    private function afterNumber(string $source, int $cursor, int $length): int
    {
        if (1 !== preg_match(self::REGEX_NUMBER, $source, $matches, 0, $cursor)) {
            return $cursor + 1;
        }

        return $cursor + strlen($matches[0]);
    }

    protected function isHalfTyped(string $source): bool
    {
        try {
            $this->tokens($source);
        } catch (SyntaxError) {
            return true;
        }

        return false;
    }

    private function valueOf(?Token $token): ?string
    {
        return null === $token ? null : (string) $token->getValue();
    }
}
