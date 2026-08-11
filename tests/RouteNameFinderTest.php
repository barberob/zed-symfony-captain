<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\RouteNameFinder;

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

        [$line, $character] = $this->positionIn($source, 'app_post_show');

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

        [$line, $character] = $this->positionIn($source, 'app_home');

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

        [$line, $character] = $this->positionIn($source, 'redirectToRoute');

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character + 2);

        self::assertNull($occurrence);
    }

    public function testFindAtReturnsNullOutsideStringOnSameLine(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generateUrl('app_home');
        PHP;

        [$line, $character] = $this->positionIn($source, 'generateUrl');

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character);

        self::assertNull($occurrence);
    }

    public function testFindAtReturnsNullJustAfterClosingQuote(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_home');
        PHP;

        [$line, $character] = $this->positionIn($source, "'app_home'");

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character + 12);

        self::assertNull($occurrence);
    }

    /**
     * Returns the 0-based line and character of the first occurrence of the
     * given needle in the source, where character is expressed in UTF-16 code
     * units as LSP positions are.
     *
     * @return array{0: int, 1: int}
     */
    private function positionIn(string $source, string $needle): array
    {
        $lines = explode("\n", $source);

        foreach ($lines as $line => $text) {
            $offset = strpos($text, $needle);

            if (false !== $offset) {
                return [$line, $offset];
            }
        }

        self::fail(sprintf('Needle "%s" not found in source.', $needle));
    }
}
