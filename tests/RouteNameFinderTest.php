<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\RouteNameFinder;
use SymfonyCaptain\Tests\PositionTestHelper;

final class RouteNameFinderTest extends TestCase
{
    public function testFindsRouteNameInRedirectToRouteCall(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_post_show');
        PHP;

        $occurrences = (new RouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('app_post_show', $occurrences[0]->name);
    }

    public function testFindsRouteNameInGenerateUrlCall(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generateUrl('app_home', ['foo' => 'bar']);
        PHP;

        $occurrences = (new RouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('app_home', $occurrences[0]->name);
    }

    public function testFindsRouteNameInGenerateCall(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $urlGenerator->generate('app_post_index');
        PHP;

        $occurrences = (new RouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('app_post_index', $occurrences[0]->name);
    }

    public function testFindsMultipleRouteNamesAcrossCalls(): void
    {
        $source = <<<'PHP'
        <?php

        $a = $this->redirectToRoute('app_home');
        $b = $this->generateUrl('app_post_show');
        PHP;

        $occurrences = (new RouteNameFinder())->find($source);

        self::assertCount(2, $occurrences);
        self::assertSame('app_home', $occurrences[0]->name);
        self::assertSame('app_post_show', $occurrences[1]->name);
    }

    public function testIgnoresRouteLikeStringNotPassedToRecognizedMethod(): void
    {
        $source = <<<'PHP'
        <?php

        $name = 'app_home';

        echo $name;
        PHP;

        self::assertSame([], (new RouteNameFinder())->find($source));
    }

    public function testIgnoresRouteLikeStringInUnknownMethod(): void
    {
        $source = <<<'PHP'
        <?php

        $this->log('app_home');
        PHP;

        self::assertSame([], (new RouteNameFinder())->find($source));
    }

    public function testIgnoresRouteLikeStringInRouteAttribute(): void
    {
        $source = <<<'PHP'
        <?php

        #[Route('/posts/{id}', name: 'app_post_show')]
        public function show(): void
        {
        }
        PHP;

        self::assertSame([], (new RouteNameFinder())->find($source));
    }

    public function testIgnoresMethodNameWithoutCall(): void
    {
        $source = <<<'PHP'
        <?php

        $method = 'redirectToRoute';
        PHP;

        self::assertSame([], (new RouteNameFinder())->find($source));
    }

    public function testIgnoresNonStringFirstArgument(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generateUrl($routeName);
        PHP;

        self::assertSame([], (new RouteNameFinder())->find($source));
    }

    public function testFindAtMatchesCursorOnRouteName(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_post_show');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_post_show');

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character + 5);

        self::assertNotNull($occurrence);
        self::assertSame('app_post_show', $occurrence->name);
    }

    public function testFindAtMatchesCursorOnOpeningQuote(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_home');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_home');

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character - 1);

        self::assertNotNull($occurrence);
        self::assertSame('app_home', $occurrence->name);
    }

    public function testFindAtReturnsNullOnMethodName(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_home');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'redirectToRoute');

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character + 2);

        self::assertNull($occurrence);
    }

    public function testFindAtReturnsNullOutsideStringOnSameLine(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generateUrl('app_home');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'generateUrl');

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character);

        self::assertNull($occurrence);
    }

    public function testFindAtReturnsNullJustAfterClosingQuote(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_home');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, "'app_home'");

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character + 12);

        self::assertNull($occurrence);
    }

    public function testFindAtMapsMultibyteCharactersToUtf16Units(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generateUrl('héllo🎉app_home');
        PHP;

        $lines = explode("\n", $source);
        $needleLine = null;
        $needleByteOffset = null;

        foreach ($lines as $line => $text) {
            $offset = strpos($text, 'app_home');

            if (false !== $offset) {
                $needleLine = $line;
                $needleByteOffset = $offset;

                break;
            }
        }

        self::assertNotNull($needleLine);
        self::assertNotNull($needleByteOffset);

        $prefix = substr($lines[$needleLine], 0, $needleByteOffset);
        $character = (int) (strlen(mb_convert_encoding($prefix, 'UTF-16LE', 'UTF-8')) / 2);

        $occurrence = (new RouteNameFinder())->findAt($source, $needleLine, $character);

        self::assertNotNull($occurrence);
        self::assertSame('héllo🎉app_home', $occurrence->name);
    }

    public function testIsAtRouteReferenceMatchesCursorOnRouteName(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_post_show');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_post_show');

        self::assertTrue((new RouteNameFinder())->isAtRouteReference($source, $line, $character + 5));
    }

    public function testIsAtRouteReferenceMatchesHalfTypedSource(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->generate('use
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'use');

        self::assertTrue((new RouteNameFinder())->isAtRouteReference($source, $line, $character + 3));
    }

    public function testIsAtRouteReferenceMatchesOpeningQuoteAlone(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->generate('
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, "generate('");

        self::assertTrue((new RouteNameFinder())->isAtRouteReference($source, $line, $character + 10));
    }

    public function testIsAtRouteReferenceFalseOnMethodName(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_home');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'redirectToRoute');

        self::assertFalse((new RouteNameFinder())->isAtRouteReference($source, $line, $character + 2));
    }

    public function testIsAtRouteReferenceFalseOnNonRouteString(): void
    {
        $source = <<<'PHP'
        <?php

        $name = 'app_home';
        PHP;

        self::assertFalse((new RouteNameFinder())->isAtRouteReference($source, ...PositionTestHelper::positionIn($source, 'app_home')));
    }

    public function testIsAtRouteReferenceFalseOnHalfTypedNonStringArgument(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generateUrl($routeNam
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'routeNam');

        self::assertFalse((new RouteNameFinder())->isAtRouteReference($source, $line, $character + 8));
    }

    public function testIsAtRouteReferenceFalseInCommentContainingGenerateCall(): void
    {
        $source = <<<'PHP'
        <?php

        // $this->generate('app_home')
        PHP;

        self::assertFalse((new RouteNameFinder())->isAtRouteReference($source, ...PositionTestHelper::positionIn($source, 'app_home')));
    }
}
