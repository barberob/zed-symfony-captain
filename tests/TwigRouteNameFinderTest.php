<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\TwigRouteNameFinder;
use SymfonyCaptain\Tests\PositionTestHelper;

final class TwigRouteNameFinderTest extends TestCase
{
    public function testFindsRouteNameInOutputTagWithSingleQuotes(): void
    {
        $source = <<<'TWIG'
        <a href="{{ path('app_post_show') }}">show</a>
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('app_post_show', $occurrences[0]->name);
    }

    public function testFindsRouteNameInOutputTagWithDoubleQuotes(): void
    {
        $source = <<<'TWIG'
        {{ url("app_home") }}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('app_home', $occurrences[0]->name);
    }

    public function testFindsRouteNameInLogicBlock(): void
    {
        $source = <<<'TWIG'
        {% set u = path('app_post_index') %}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('app_post_index', $occurrences[0]->name);
    }

    public function testFindsRouteNamesAcrossOutputAndLogicTags(): void
    {
        $source = <<<'TWIG'
        {{ path('app_home') }}
        {% set u = url("app_post_show") %}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(2, $occurrences);
        self::assertSame('app_home', $occurrences[0]->name);
        self::assertSame('app_post_show', $occurrences[1]->name);
    }

    public function testFindsRouteNameInWhitespaceTrimmedTags(): void
    {
        $source = <<<'TWIG'
        {{- path('app_home') -}}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('app_home', $occurrences[0]->name);
    }

    public function testIgnoresMethodCallOnVariable(): void
    {
        $source = <<<'TWIG'
        {{ app.request.path('app_home') }}
        TWIG;

        self::assertSame([], (new TwigRouteNameFinder())->find($source));
    }

    public function testIgnoresRouteLikeStringNotInCall(): void
    {
        $source = <<<'TWIG'
        <a href="/app_home">home</a>
        TWIG;

        self::assertSame([], (new TwigRouteNameFinder())->find($source));
    }

    public function testIgnoresRouteLikeStringInUnknownFunction(): void
    {
        $source = <<<'TWIG'
        {{ log('app_home') }}
        TWIG;

        self::assertSame([], (new TwigRouteNameFinder())->find($source));
    }

    public function testIgnoresFilterApplicationOfFunctionName(): void
    {
        $source = <<<'TWIG'
        {{ 'app_home'|path }}
        TWIG;

        self::assertSame([], (new TwigRouteNameFinder())->find($source));
    }

    public function testIgnoresNonStringFirstArgument(): void
    {
        $source = <<<'TWIG'
        {{ path(target) }}
        TWIG;

        self::assertSame([], (new TwigRouteNameFinder())->find($source));
    }

    public function testIgnoresNamedArgumentRouteName(): void
    {
        $source = <<<'TWIG'
        {{ path(name = 'app_home') }}
        TWIG;

        self::assertSame([], (new TwigRouteNameFinder())->find($source));
    }

    public function testHandlesEmptyStringAfterOpeningQuote(): void
    {
        $source = <<<'TWIG'
        {{ path('') }}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('', $occurrences[0]->name);
    }

    public function testFindsRouteNameSurroundedByRawHtml(): void
    {
        $source = <<<'TWIG'
        <style>.a{color:red}</style><script>var x = "{ not twig }";</script>{{ path('app_home') }}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('app_home', $occurrences[0]->name);
    }

    public function testFindsRouteNameAfterTwigComment(): void
    {
        $source = <<<'TWIG'
        {{ path('app_home') }}{# a comment #}{{ url('app_post_show') }}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(2, $occurrences);
        self::assertSame('app_home', $occurrences[0]->name);
        self::assertSame('app_post_show', $occurrences[1]->name);
    }

    public function testFindAtMatchesCursorOnRouteName(): void
    {
        $source = <<<'TWIG'
        <a href="{{ path('app_post_show') }}">show</a>
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_post_show');

        $occurrence = (new TwigRouteNameFinder())->findAt($source, $line, $character + 5);

        self::assertNotNull($occurrence);
        self::assertSame('app_post_show', $occurrence->name);
    }

    public function testFindAtMatchesCursorOnOpeningQuote(): void
    {
        $source = <<<'TWIG'
        {{ path('app_home') }}
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_home');

        $occurrence = (new TwigRouteNameFinder())->findAt($source, $line, $character - 1);

        self::assertNotNull($occurrence);
        self::assertSame('app_home', $occurrence->name);
    }

    public function testFindAtReturnsNullOnFunctionName(): void
    {
        $source = <<<'TWIG'
        {{ path('app_home') }}
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'path');

        $occurrence = (new TwigRouteNameFinder())->findAt($source, $line, $character + 2);

        self::assertNull($occurrence);
    }

    public function testFindAtReturnsNullOutsideStringOnSameLine(): void
    {
        $source = <<<'TWIG'
        <a href="{{ path('app_home') }}">show</a>
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'path');

        $occurrence = (new TwigRouteNameFinder())->findAt($source, $line, $character);

        self::assertNull($occurrence);
    }

    public function testFindAtReturnsNullJustAfterClosingQuote(): void
    {
        $source = <<<'TWIG'
        {{ path('app_home') }}
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, "'app_home'");

        $occurrence = (new TwigRouteNameFinder())->findAt($source, $line, $character + 12);

        self::assertNull($occurrence);
    }

    public function testExposesByteRangeOfRouteNameString(): void
    {
        $source = <<<'TWIG'
        {{ path('app_home') }}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame("'app_home'", substr($source, $occurrences[0]->startOffset, $occurrences[0]->endOffset - $occurrences[0]->startOffset));
    }

    public function testExposesByteRangeOfDoubleQuotedRouteNameString(): void
    {
        $source = <<<'TWIG'
        {% set u = url("app_post_index") %}
        TWIG;

        $occurrences = (new TwigRouteNameFinder())->find($source);

        self::assertCount(1, $occurrences);
        self::assertSame('"app_post_index"', substr($source, $occurrences[0]->startOffset, $occurrences[0]->endOffset - $occurrences[0]->startOffset));
    }

    public function testFindAtMapsMultibyteCharactersToUtf16Units(): void
    {
        $source = <<<'TWIG'
        <a title="héllo🎉" href="{{ path('héllo🎉app_home') }}">x</a>
        TWIG;

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

        $occurrence = (new TwigRouteNameFinder())->findAt($source, $needleLine, $character);

        self::assertNotNull($occurrence);
        self::assertSame('héllo🎉app_home', $occurrence->name);
    }

    public function testIsAtRouteReferenceMatchesCursorOnRouteName(): void
    {
        $source = <<<'TWIG'
        {{ path('app_post_show') }}
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_post_show');

        self::assertTrue((new TwigRouteNameFinder())->isAtRouteReference($source, $line, $character + 5));
    }

    public function testIsAtRouteReferenceMatchesHalfTypedSource(): void
    {
        $source = <<<'TWIG'
        {{ path('use
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'use');

        self::assertTrue((new TwigRouteNameFinder())->isAtRouteReference($source, $line, $character + 3));
    }

    public function testIsAtRouteReferenceMatchesHalfTypedDoubleQuotedSource(): void
    {
        $source = <<<'TWIG'
        {{ url("app_pos
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_pos');

        self::assertTrue((new TwigRouteNameFinder())->isAtRouteReference($source, $line, $character + 6));
    }

    public function testIsAtRouteReferenceMatchesOpeningQuoteAlone(): void
    {
        $source = <<<'TWIG'
        {{ path('
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, "path('");

        self::assertTrue((new TwigRouteNameFinder())->isAtRouteReference($source, $line, $character + 6));
    }

    public function testIsAtRouteReferenceFalseOnFunctionName(): void
    {
        $source = <<<'TWIG'
        {{ path('app_home') }}
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'path');

        self::assertFalse((new TwigRouteNameFinder())->isAtRouteReference($source, $line, $character + 2));
    }

    public function testIsAtRouteReferenceFalseOnMethodCall(): void
    {
        $source = <<<'TWIG'
        {{ app.request.path('app_home') }}
        TWIG;

        self::assertFalse((new TwigRouteNameFinder())->isAtRouteReference($source, ...PositionTestHelper::positionIn($source, 'app_home')));
    }

    public function testIsAtRouteReferenceFalseOnNonRouteString(): void
    {
        $source = <<<'TWIG'
        {{ '/app_home' }}
        TWIG;

        self::assertFalse((new TwigRouteNameFinder())->isAtRouteReference($source, ...PositionTestHelper::positionIn($source, '/app_home')));
    }

    public function testIsAtRouteReferenceFalseOnHalfTypedNonStringArgument(): void
    {
        $source = <<<'TWIG'
        {{ path(target
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'target');

        self::assertFalse((new TwigRouteNameFinder())->isAtRouteReference($source, $line, $character + 6));
    }

    public function testIsAtRouteReferenceFalseInsideComment(): void
    {
        $source = <<<'TWIG'
        {# {{ path('app_home') }} #}
        TWIG;

        self::assertFalse((new TwigRouteNameFinder())->isAtRouteReference($source, ...PositionTestHelper::positionIn($source, 'app_home')));
    }

    public function testIsAtRouteReferenceFalseOnHalfTypedMethodCall(): void
    {
        $source = <<<'TWIG'
        {{ app.request.path('use
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'use');

        self::assertFalse((new TwigRouteNameFinder())->isAtRouteReference($source, $line, $character + 3));
    }

    public function testIsAtRouteReferenceFalseOnHalfTypedFilterApplication(): void
    {
        $source = <<<'TWIG'
        {{ 'app_home'|path('use
        TWIG;

        [$line, $character] = PositionTestHelper::positionIn($source, 'use');

        self::assertFalse((new TwigRouteNameFinder())->isAtRouteReference($source, $line, $character + 3));
    }
}
