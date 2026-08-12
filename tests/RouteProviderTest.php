<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\ControllerResolver;
use SymfonyCaptain\Lsp\DebugRouterParser;
use SymfonyCaptain\Lsp\RouteEntry;
use SymfonyCaptain\Lsp\RouteProvider;
use SymfonyCaptain\Lsp\RouteProviderException;

final class RouteProviderTest extends TestCase
{
    public function testBuildReturnsRoutesWithLocationsFromFixture(): void
    {
        $entries = $this->buildIndex(__DIR__ . '/Fixture/Project');
        $byName = $this->indexByName($entries);

        self::assertCount(6, $entries);

        $home = $byName['app_home'];
        self::assertSame('/', $home->route->path);
        self::assertSame(['GET', 'HEAD'], $home->route->methods);
        self::assertSame('App\\Controller\\HomeController::index', $home->route->controller);
        self::assertNotNull($home->location);
        self::assertSame(__DIR__ . '/Fixture/Project/src/Controller/HomeController.php', $home->location->file);
        self::assertSame(12, $home->location->line);

        $show = $byName['app_post_show'];
        self::assertSame('/posts/{id}', $show->route->path);
        self::assertSame(['GET', 'HEAD'], $show->route->methods);
        self::assertNotNull($show->location);
        self::assertSame(__DIR__ . '/Fixture/Project/src/Controller/PostController.php', $show->location->file);
        self::assertSame(17, $show->location->line);
    }

    public function testUnresolvableControllersAreKeptWithoutLocation(): void
    {
        $entries = $this->buildIndex(__DIR__ . '/Fixture/Project');
        $byName = $this->indexByName($entries);

        self::assertNull($byName['app_legacy_home']->location);
        self::assertSame('App\\Controller\\LegacyController::home', $byName['app_legacy_home']->route->controller);

        self::assertNull($byName['app_callback']->location);
        self::assertSame('', $byName['app_callback']->route->controller);
    }

    public function testBuildThrowsWhenConsoleIsMissing(): void
    {
        $this->expectException(RouteProviderException::class);

        $this->buildIndex(__DIR__ . '/Fixture/Empty');
    }

    public function testBuildThrowsWhenDebugRouterFails(): void
    {
        $this->expectException(RouteProviderException::class);

        $this->buildIndex(__DIR__ . '/Fixture/Broken');
    }

    public function testIsSymfonyProjectReturnsTrueForProjectFixture(): void
    {
        self::assertTrue($this->provider(__DIR__ . '/Fixture/Project')->isSymfonyProject());
    }

    public function testIsSymfonyProjectReturnsFalseWithoutConsole(): void
    {
        self::assertFalse($this->provider(__DIR__ . '/Fixture/Empty')->isSymfonyProject());
    }

    public function testIsSymfonyProjectReturnsFalseWithoutBootableKernel(): void
    {
        self::assertFalse($this->provider(__DIR__ . '/Fixture/ConsoleOnly')->isSymfonyProject());
    }

    public function testIsRouteDefinitionFileMatchesControllerAndRoutesConfig(): void
    {
        $provider = $this->provider(__DIR__ . '/Fixture/Project');
        $root = __DIR__ . '/Fixture/Project';

        self::assertTrue($provider->isRouteDefinitionFile($root . '/src/Controller/PostController.php'));
        self::assertTrue($provider->isRouteDefinitionFile($root . '/src/Controller/Admin/UserController.php'));
        self::assertTrue($provider->isRouteDefinitionFile($root . '/config/routes.yaml'));
        self::assertTrue($provider->isRouteDefinitionFile($root . '/config/routes/annotations.yaml'));
        self::assertFalse($provider->isRouteDefinitionFile($root . '/src/Entity/Post.php'));
        self::assertFalse($provider->isRouteDefinitionFile($root . '/templates/index.html.twig'));
    }

    /**
     * @return list<RouteEntry>
     */
    private function buildIndex(string $projectRoot): array
    {
        return $this->provider($projectRoot)->build();
    }

    private function provider(string $projectRoot): RouteProvider
    {
        return new RouteProvider(
            projectRoot: $projectRoot,
            parser: new DebugRouterParser(),
            controllerResolver: new ControllerResolver($projectRoot),
        );
    }

    /**
     * @param list<RouteEntry> $entries
     *
     * @return array<string, RouteEntry>
     */
    private function indexByName(array $entries): array
    {
        $byName = [];

        foreach ($entries as $entry) {
            $byName[$entry->route->name] = $entry;
        }

        return $byName;
    }
}
