<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

final class PositionTestHelper
{
    /**
     * Returns the 0-based line and character of the first occurrence of the
     * given needle in the source.
     *
     * @return array{0: int, 1: int}
     */
    public static function positionIn(string $source, string $needle): array
    {
        $lines = explode("\n", $source);

        foreach ($lines as $line => $text) {
            $offset = strpos($text, $needle);

            if (false !== $offset) {
                return [$line, $offset];
            }
        }

        throw new \RuntimeException(sprintf('Needle "%s" not found in source.', $needle));
    }
}
