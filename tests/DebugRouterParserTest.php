<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\DebugRouterParser;

final class DebugRouterParserTest extends TestCase
{
    public function testParseReturnsRouteIndex(): void
    {
        $json = json_encode([
            'app_home' => [
                'path' => '/',
                'method' => 'GET|HEAD',
                'defaults' => ['_controller' => 'App\\Controller\\HomeController::index'],
            ],
            'app_post_show' => [
                'path' => '/posts/{id}',
                'method' => 'GET',
                'defaults' => ['_controller' => 'App\\Controller\\PostController::show'],
            ],
        ], JSON_THROW_ON_ERROR);

        $routes = (new DebugRouterParser())->parse($json);

        self::assertCount(2, $routes);
        self::assertSame('app_home', $routes[0]->name);
        self::assertSame('/', $routes[0]->path);
        self::assertSame(['GET', 'HEAD'], $routes[0]->methods);
        self::assertSame('App\\Controller\\HomeController::index', $routes[0]->controller);
        self::assertSame('/posts/{id}', $routes[1]->path);
        self::assertSame(['GET'], $routes[1]->methods);
    }

    public function testParseAnyMethodReturnsEmptyMethodsAndController(): void
    {
        $json = json_encode([
            'app_callback' => [
                'path' => '/callback',
                'method' => 'ANY',
                'defaults' => [],
            ],
        ], JSON_THROW_ON_ERROR);

        $routes = (new DebugRouterParser())->parse($json);

        self::assertCount(1, $routes);
        self::assertSame([], $routes[0]->methods);
        self::assertSame('', $routes[0]->controller);
    }

    public function testParseInvalidJsonReturnsEmptyIndex(): void
    {
        self::assertSame([], (new DebugRouterParser())->parse('not-json'));
    }
}
