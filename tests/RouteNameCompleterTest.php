<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\Route;
use SymfonyCaptain\Lsp\RouteEntry;
use SymfonyCaptain\Lsp\RouteIndex;
use SymfonyCaptain\Lsp\RouteNameCompleter;
use SymfonyCaptain\Lsp\RouteNameFinder;
use SymfonyCaptain\Tests\PositionTestHelper;

final class RouteNameCompleterTest extends TestCase
{
    private const ITEM_KIND_CONSTANT = 21;

    public function testCompleteReturnsEveryRouteSortedAlphabetically(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_home');
        PHP;

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, ...PositionTestHelper::positionIn($source, 'app_home'));

        self::assertSame(['app_callback', 'app_home', 'app_post_show'], $this->labels($items));
    }

    public function testItemShape(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_home');
        PHP;

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, ...PositionTestHelper::positionIn($source, 'app_home'));

        $home = $this->itemByLabel($items, 'app_home');
        self::assertNotNull($home);
        self::assertSame('app_home', $home['label']);
        self::assertSame(self::ITEM_KIND_CONSTANT, $home['kind']);
        self::assertSame('GET|HEAD /', $home['detail']);
        self::assertSame('App\Controller\HomeController::index', $home['documentation']);
    }

    public function testRouteWithoutControllerStillCompletable(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_home');
        PHP;

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, ...PositionTestHelper::positionIn($source, 'app_home'));

        $callback = $this->itemByLabel($items, 'app_callback');
        self::assertNotNull($callback);
        self::assertSame('ANY /callback', $callback['detail']);
        self::assertSame('', $callback['documentation']);
    }

    public function testEmptyStringAfterOpeningQuoteTriggers(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, "redirectToRoute('");

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, $line, $character + 17);

        self::assertSame(['app_callback', 'app_home', 'app_post_show'], $this->labels($items));
    }

    public function testHalfTypedSourceTriggersFallback(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->generate('use
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'use');

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, $line, $character + 3);

        self::assertSame(['app_callback', 'app_home', 'app_post_show'], $this->labels($items));
    }

    public function testOpeningQuoteAloneTriggersFallback(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->generate('
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, "generate('");

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, $line, $character + 10);

        self::assertSame(['app_callback', 'app_home', 'app_post_show'], $this->labels($items));
    }

    public function testStaysSilentOnHalfTypedNonStringArgument(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generateUrl($routeNam
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'routeNam');

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, $line, $character + 8);

        self::assertSame([], $items);
    }

    public function testStaysSilentInCommentContainingGenerateCall(): void
    {
        $source = <<<'PHP'
        <?php

        // $this->generate('app_home')
        PHP;

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, ...PositionTestHelper::positionIn($source, 'app_home'));

        self::assertSame([], $items);
    }

    public function testStaysSilentInPlainStringContainingGenerateCall(): void
    {
        $source = <<<'PHP'
        <?php

        $message = "call generate('app_home')";
        PHP;

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, ...PositionTestHelper::positionIn($source, 'app_home'));

        self::assertSame([], $items);
    }

    public function testStaysSilentOnMethodName(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_home');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, 'generate');

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, $line, $character + 2);

        self::assertSame([], $items);
    }

    public function testStaysSilentJustAfterClosingQuote(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_home');
        PHP;

        [$line, $character] = PositionTestHelper::positionIn($source, "'app_home'");

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, $line, $character + 11);

        self::assertSame([], $items);
    }

    public function testStaysSilentOnNonRouteString(): void
    {
        $source = <<<'PHP'
        <?php

        $name = 'app_home';
        PHP;

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, ...PositionTestHelper::positionIn($source, 'app_home'));

        self::assertSame([], $items);
    }

    public function testStaysSilentOnNonFirstArgument(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generateUrl('app_home', 'app_post_show');
        PHP;

        $items = (new RouteNameCompleter($this->index(), new RouteNameFinder()))->complete($source, ...PositionTestHelper::positionIn($source, 'app_post_show'));

        self::assertSame([], $items);
    }

    public function testEmptyIndexReturnsEmptyItems(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_home');
        PHP;

        $items = (new RouteNameCompleter(new RouteIndex([]), new RouteNameFinder()))->complete($source, ...PositionTestHelper::positionIn($source, 'app_home'));

        self::assertSame([], $items);
    }

    private function index(): RouteIndex
    {
        return new RouteIndex([
            new RouteEntry(new Route('app_post_show', ['GET', 'HEAD'], '/posts/{id}', 'App\Controller\PostController::show'), null),
            new RouteEntry(new Route('app_home', ['GET', 'HEAD'], '/', 'App\Controller\HomeController::index'), null),
            new RouteEntry(new Route('app_callback', [], '/callback', ''), null),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<string>
     */
    private function labels(array $items): array
    {
        return array_map(static fn (array $item): string => $item['label'], $items);
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>|null
     */
    private function itemByLabel(array $items, string $label): ?array
    {
        foreach ($items as $item) {
            if ($item['label'] === $label) {
                return $item;
            }
        }

        return null;
    }
}
