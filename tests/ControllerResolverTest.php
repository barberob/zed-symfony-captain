<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\ControllerResolver;

final class ControllerResolverTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = __DIR__ . '/Fixture/Project';
    }

    public function testResolveMapsControllerStringToFileAndLine(): void
    {
        $resolver = new ControllerResolver($this->projectRoot);

        $location = $resolver->resolve('App\\Controller\\PostController::show');

        self::assertNotNull($location);
        self::assertSame($this->projectRoot . '/src/Controller/PostController.php', $location->file);
        self::assertSame(17, $location->line);
    }

    public function testResolveUnknownClassReturnsNull(): void
    {
        $resolver = new ControllerResolver($this->projectRoot);

        self::assertNull($resolver->resolve('App\\Controller\\LegacyController::home'));
    }

    public function testResolveUnknownMethodReturnsNull(): void
    {
        $resolver = new ControllerResolver($this->projectRoot);

        self::assertNull($resolver->resolve('App\\Controller\\HomeController::missing'));
    }

    public function testResolveNonClassControllerReturnsNull(): void
    {
        $resolver = new ControllerResolver($this->projectRoot);

        self::assertNull($resolver->resolve('App\\Controller\\HomeController'));
        self::assertNull($resolver->resolve('closure'));
    }
}
