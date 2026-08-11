<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\ControllerResolver;
use SymfonyCaptain\Lsp\DebugRouterParser;
use SymfonyCaptain\Lsp\RouteIndex;
use SymfonyCaptain\Lsp\RouteProvider;
use SymfonyCaptain\Lsp\Uri;
use SymfonyCaptain\Lsp\WorkspaceSymbols;

final class WorkspaceSymbolsTest extends TestCase
{
    public function testBuildReturnsOneSymbolPerRoute(): void
    {
        $symbols = $this->buildSymbols(__DIR__ . '/Fixture/Project');

        self::assertCount(6, $symbols);

        $home = $this->symbolByName($symbols, 'Route: app_home');
        self::assertSame('GET|HEAD /', $home['detail']);
        self::assertSame(Uri::fromPath(__DIR__ . '/Fixture/Project/src/Controller/HomeController.php'), $home['location']['uri']);
        self::assertSame(11, $home['location']['range']['start']['line']);

        $show = $this->symbolByName($symbols, 'Route: app_post_show');
        self::assertSame('GET|HEAD /posts/{id}', $show['detail']);
        self::assertSame(Uri::fromPath(__DIR__ . '/Fixture/Project/src/Controller/PostController.php'), $show['location']['uri']);
        self::assertSame(16, $show['location']['range']['start']['line']);
    }

    public function testUnresolvableControllersAreStillReturned(): void
    {
        $symbols = $this->buildSymbols(__DIR__ . '/Fixture/Project');

        $legacy = $this->symbolByName($symbols, 'Route: app_legacy_home');
        self::assertNotNull($legacy);
        self::assertSame('ANY /legacy', $legacy['detail']);

        $callback = $this->symbolByName($symbols, 'Route: app_callback');
        self::assertNotNull($callback);
        self::assertSame('ANY /callback', $callback['detail']);
    }

    public function testEmptyIndexReturnsEmptySymbols(): void
    {
        $symbols = (new WorkspaceSymbols(__DIR__ . '/Fixture/Empty'))->build(new RouteIndex([]));

        self::assertSame([], $symbols);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSymbols(string $projectRoot): array
    {
        $provider = new RouteProvider(
            projectRoot: $projectRoot,
            parser: new DebugRouterParser(),
            controllerResolver: new ControllerResolver($projectRoot),
        );

        return (new WorkspaceSymbols($projectRoot))->build(new RouteIndex($provider->build()));
    }

    /**
     * @param list<array<string, mixed>> $symbols
     *
     * @return array<string, mixed>|null
     */
    private function symbolByName(array $symbols, string $name): ?array
    {
        foreach ($symbols as $symbol) {
            if ($symbol['name'] === $name) {
                return $symbol;
            }
        }

        return null;
    }
}
