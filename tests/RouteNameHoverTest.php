<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\Route;
use SymfonyCaptain\Lsp\RouteEntry;
use SymfonyCaptain\Lsp\RouteIndex;
use SymfonyCaptain\Lsp\RouteNameFinder;
use SymfonyCaptain\Lsp\RouteNameHover;
use SymfonyCaptain\Tests\PositionTestHelper;

final class RouteNameHoverTest extends TestCase
{
    public function testHoverOnRouteNameReturnsMarkdownContentsAndRange(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_post_show');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_post_show');

        $hover = (new RouteNameHover($this->index(), new RouteNameFinder()))->hover($source, $line, $character + 5);

        self::assertNotNull($hover);
        self::assertSame('markdown', $hover['contents']['kind']);
        self::assertSame("**app_post_show**\n\n`GET|HEAD /posts/{id}`\n\n`App\\Controller\\PostController::show`", $hover['contents']['value']);

        [$stringLine, $stringStart] = PositionTestHelper::positionIn($source, "'app_post_show'");
        self::assertSame(['line' => $stringLine, 'character' => $stringStart], $hover['range']['start']);
        self::assertSame(['line' => $stringLine, 'character' => $stringStart + strlen("'app_post_show'")], $hover['range']['end']);
    }

    public function testHoverOnUnknownRouteReturnsNull(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_not_defined');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_not_defined');

        $hover = (new RouteNameHover($this->index(), new RouteNameFinder()))->hover($source, $line, $character + 2);

        self::assertNull($hover);
    }

    public function testHoverOutsideRouteReferenceReturnsNull(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_post_show');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'generate');

        $hover = (new RouteNameHover($this->index(), new RouteNameFinder()))->hover($source, $line, $character + 2);

        self::assertNull($hover);
    }

    public function testHoverOnRouteWithoutControllerOmitsControllerLine(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_callback');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_callback');

        $hover = (new RouteNameHover($this->index(), new RouteNameFinder()))->hover($source, $line, $character + 2);

        self::assertNotNull($hover);
        self::assertSame("**app_callback**\n\n`ANY /callback`", $hover['contents']['value']);
    }

    public function testHoverOnEmptyIndexReturnsNull(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_post_show');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'app_post_show');

        $hover = (new RouteNameHover(new RouteIndex([]), new RouteNameFinder()))->hover($source, $line, $character + 2);

        self::assertNull($hover);
    }

    private function index(): RouteIndex
    {
        return new RouteIndex([
            new RouteEntry(new Route('app_post_show', ['GET', 'HEAD'], '/posts/{id}', 'App\Controller\PostController::show'), null),
            new RouteEntry(new Route('app_callback', [], '/callback', ''), null),
        ]);
    }
}
