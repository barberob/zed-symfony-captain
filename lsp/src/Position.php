<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class Position
{
    /**
     * Converts an LSP position (0-based line and character in UTF-16 code
     * units) into a byte offset into the source.
     */
    public static function toByteOffset(string $source, int $line, int $character): int
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
            $units += self::utf16Units($source[$offset]);
            $offset += self::utf8Bytes($source[$offset]);
        }

        return $offset;
    }

    private static function utf8Bytes(string $firstByte): int
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

    private static function utf16Units(string $firstByte): int
    {
        return 0xF0 <= ord($firstByte) ? 2 : 1;
    }
}
