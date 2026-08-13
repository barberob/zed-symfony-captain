<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\RouteReferenceLocation;
use SymfonyCaptain\Lsp\RouteReferenceScanner;

final class RouteReferenceScannerTest extends TestCase
{
    public function testFindReturnsPhpAndTwigReferencesForWantedNames(): void
    {
        $locations = $this->scanner()->find(['app_post_show']);

        self::assertSame([
            ['app_post_show', 'src/RouteCalls.php', 11],
            ['app_post_show', 'templates/index.html.twig', 5],
        ], $this->triples($locations));
    }

    public function testFindReturnsReferenceForEveryWantedName(): void
    {
        $locations = $this->scanner()->find(['app_home', 'app_post_index']);

        self::assertSame([
            ['app_home', 'src/RouteCalls.php', 16],
            ['app_home', 'templates/index.html.twig', 6],
            ['app_post_index', 'templates/index.html.twig', 10],
            ['app_post_index', 'templates/layout.twig.html', 3],
        ], $this->triples($locations));
    }

    public function testFindIgnoresUnwantedNames(): void
    {
        self::assertSame([], $this->scanner()->find(['app_never_defined']));
    }

    public function testFindDoesNotMatchMethodCallInTwig(): void
    {
        $locations = $this->scanner()->find(['app_home']);

        self::assertSame([
            ['app_home', 'src/RouteCalls.php', 16],
            ['app_home', 'templates/index.html.twig', 6],
        ], $this->triples($locations));
    }

    private function scanner(): RouteReferenceScanner
    {
        return new RouteReferenceScanner(__DIR__ . '/Fixture/Project');
    }

    /**
     * @param list<RouteReferenceLocation> $locations
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function triples(array $locations): array
    {
        return array_map(
            static fn (RouteReferenceLocation $location): array => [$location->name, $location->file, $location->line],
            $locations,
        );
    }
}
