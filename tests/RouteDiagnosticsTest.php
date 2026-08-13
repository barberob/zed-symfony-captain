<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\Route;
use SymfonyCaptain\Lsp\RouteDiagnostics;
use SymfonyCaptain\Lsp\RouteEntry;
use SymfonyCaptain\Lsp\RouteIndex;
use SymfonyCaptain\Lsp\RouteNameFinder;
use SymfonyCaptain\Lsp\TwigRouteNameFinder;

final class RouteDiagnosticsTest extends TestCase
{
    public function testValidReferencesProduceNoDiagnostics(): void
    {
        $source = <<<'PHP'
        <?php

        return $this->redirectToRoute('app_post_show');
        PHP;

        $diagnostics = (new RouteDiagnostics($this->index(), new RouteNameFinder()))->diagnostics($source);

        self::assertSame([], $diagnostics);
    }

    public function testDanglingReferenceProducesWarningDiagnostic(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_not_defined');
        PHP;

        $diagnostics = (new RouteDiagnostics($this->index(), new RouteNameFinder()))->diagnostics($source);

        self::assertCount(1, $diagnostics);

        $diagnostic = $diagnostics[0];
        self::assertSame(2, $diagnostic['severity']);
        self::assertSame('symfony-captain', $diagnostic['source']);
        self::assertSame("Route 'app_not_defined' does not exist.", $diagnostic['message']);

        [$line, $start] = PositionTestHelper::positionIn($source, "'app_not_defined'");
        self::assertSame(['line' => $line, 'character' => $start], $diagnostic['range']['start']);
        self::assertSame(['line' => $line, 'character' => $start + strlen("'app_not_defined'")], $diagnostic['range']['end']);
    }

    public function testMultipleDanglingReferencesProduceOneDiagnosticEach(): void
    {
        $source = <<<'PHP'
        <?php

        $url = $this->generate('app_not_defined');
        $home = $this->generateUrl('app_home');
        $other = $this->generate('app_also_missing');
        PHP;

        $diagnostics = (new RouteDiagnostics($this->index(), new RouteNameFinder()))->diagnostics($source);

        self::assertCount(2, $diagnostics);
        self::assertSame(["Route 'app_not_defined' does not exist.", "Route 'app_also_missing' does not exist."], array_column($diagnostics, 'message'));
    }

    public function testNonRouteStringProducesNoDiagnostics(): void
    {
        $source = <<<'PHP'
        <?php

        $name = 'app_not_defined';
        PHP;

        $diagnostics = (new RouteDiagnostics($this->index(), new RouteNameFinder()))->diagnostics($source);

        self::assertSame([], $diagnostics);
    }

    public function testTwigTemplateDanglingReferenceProducesDiagnostic(): void
    {
        $source = <<<'TWIG'
        <a href="{{ path('app_not_defined') }}">link</a>
        <a href="{{ url('app_post_show') }}">show</a>
        TWIG;

        $diagnostics = (new RouteDiagnostics($this->index(), new TwigRouteNameFinder()))->diagnostics($source);

        self::assertCount(1, $diagnostics);
        self::assertSame("Route 'app_not_defined' does not exist.", $diagnostics[0]['message']);

        [$line, $start] = PositionTestHelper::positionIn($source, "'app_not_defined'");
        self::assertSame(['line' => $line, 'character' => $start], $diagnostics[0]['range']['start']);
        self::assertSame(['line' => $line, 'character' => $start + strlen("'app_not_defined'")], $diagnostics[0]['range']['end']);
    }

    private function index(): RouteIndex
    {
        return new RouteIndex([
            new RouteEntry(new Route('app_post_show', ['GET', 'HEAD'], '/posts/{id}', 'App\Controller\PostController::show'), null),
            new RouteEntry(new Route('app_home', ['GET', 'HEAD'], '/', 'App\Controller\HomeController::index'), null),
        ]);
    }
}
