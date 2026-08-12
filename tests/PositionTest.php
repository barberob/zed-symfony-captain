<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\Position;

final class PositionTest extends TestCase
{
    public function testToByteOffsetWalksLinesAndCharacters(): void
    {
        $source = "<?php\n\nreturn \$this->generate('app_home');";

        self::assertSame(0, Position::toByteOffset($source, 0, 0));
        self::assertSame(7, Position::toByteOffset($source, 2, 0));
        self::assertSame(21, Position::toByteOffset($source, 2, 14));
    }

    public function testToPositionWalksLinesAndCharacters(): void
    {
        $source = "<?php\n\nreturn \$this->generate('app_home');";

        self::assertSame(['line' => 0, 'character' => 0], Position::toPosition($source, 0));
        self::assertSame(['line' => 2, 'character' => 0], Position::toPosition($source, 7));
        self::assertSame(['line' => 2, 'character' => 14], Position::toPosition($source, 21));
    }

    public function testRoundTripAcrossMultibyteSource(): void
    {
        $source = "<?php\n\n\$url = \$this->generateUrl('héllo🎉app_home');";
        $needleLine = 0;
        $needleOffset = 0;

        foreach (explode("\n", $source) as $line => $text) {
            $offset = strpos($text, 'app_home');

            if (false !== $offset) {
                $needleLine = $line;
                $needleOffset = $offset;

                break;
            }
        }

        $prefix = substr(explode("\n", $source)[$needleLine], 0, $needleOffset);
        $character = (int) (strlen(mb_convert_encoding($prefix, 'UTF-16LE', 'UTF-8')) / 2);

        $byteOffset = Position::toByteOffset($source, $needleLine, $character);
        self::assertSame(['line' => $needleLine, 'character' => $character], Position::toPosition($source, $byteOffset));
    }
}
