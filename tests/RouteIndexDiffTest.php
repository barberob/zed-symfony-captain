<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\Route;
use SymfonyCaptain\Lsp\RouteEntry;
use SymfonyCaptain\Lsp\RouteIndex;
use SymfonyCaptain\Lsp\RouteIndexDiff;

final class RouteIndexDiffTest extends TestCase
{
    public function testRemovedNamesReturnsEmptyWhenNothingRemoved(): void
    {
        $previous = $this->index(['app_home', 'app_post_show']);
        $current = $this->index(['app_home', 'app_post_show', 'app_about']);

        self::assertSame([], RouteIndexDiff::removedNames($previous, $current));
    }

    public function testRemovedNamesReturnsRemovedRouteNames(): void
    {
        $previous = $this->index(['app_home', 'app_post_show', 'app_post_create']);
        $current = $this->index(['app_home', 'app_post_create']);

        self::assertSame(['app_post_show'], RouteIndexDiff::removedNames($previous, $current));
    }

    public function testRemovedNamesReturnsAllRemovedRoutesInOrder(): void
    {
        $previous = $this->index(['app_home', 'app_post_show', 'app_legacy']);
        $current = $this->index(['app_post_show']);

        self::assertSame(['app_home', 'app_legacy'], RouteIndexDiff::removedNames($previous, $current));
    }

    public function testRemovedNamesIgnoresAddedRoutes(): void
    {
        $previous = $this->index([]);
        $current = $this->index(['app_home', 'app_post_show']);

        self::assertSame([], RouteIndexDiff::removedNames($previous, $current));
    }

    /**
     * @param list<string> $names
     */
    private function index(array $names): RouteIndex
    {
        $entries = array_map(
            static fn (string $name): RouteEntry => new RouteEntry(new Route($name, ['GET'], '/' . $name, 'App\Controller\DemoController::index'), null),
            $names,
        );

        return new RouteIndex($entries);
    }
}
