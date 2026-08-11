<?php

declare(strict_types=1);

namespace SymfonyCaptain\Tests;

use PHPUnit\Framework\TestCase;
use SymfonyCaptain\Lsp\LspServer;
use SymfonyCaptain\Lsp\MessageStream;
use SymfonyCaptain\Lsp\Uri;

final class LspServerTest extends TestCase
{
    /** @var list<string> */
    private array $tempRoots = [];

    public function testInitializeReturnsCapabilities(): void
    {
        $input = $this->createInputStream([
            $this->buildRequest(1, 'initialize', ['processId' => 123, 'rootPath' => '/project']),
        ]);
        $output = fopen('php://memory', 'r+');

        $server = new LspServer();
        $server->run(new MessageStream($input, $output));

        rewind($output);
        $raw = stream_get_contents($output);

        $response = $this->parseLastMessage($raw);

        self::assertSame(1, $response['id']);
        self::assertArrayHasKey('capabilities', $response['result']);
        self::assertTrue($response['result']['capabilities']['workspaceSymbolProvider']);
        self::assertTrue($response['result']['capabilities']['definitionProvider']);
    }

    public function testShutdownThenExitStopsServer(): void
    {
        $input = $this->createInputStream([
            $this->buildRequest(2, 'shutdown'),
            $this->buildNotification('exit'),
        ]);
        $output = fopen('php://memory', 'r+');

        $server = new LspServer();
        $server->run(new MessageStream($input, $output));

        rewind($output);
        $raw = stream_get_contents($output);

        $messages = $this->parseMessages($raw);

        self::assertCount(1, $messages);
        self::assertSame(2, $messages[0]['id']);
        self::assertNull($messages[0]['result']);
    }

    public function testInitializedNotificationDoesNotProduceResponse(): void
    {
        $input = $this->createInputStream([
            $this->buildNotification('initialized'),
        ]);
        $output = fopen('php://memory', 'r+');

        $server = new LspServer();
        $server->run(new MessageStream($input, $output));

        rewind($output);
        $raw = stream_get_contents($output);

        self::assertSame('', $raw);
    }

    public function testWorkspaceSymbolsRequestReturnsOneSymbolPerRoute(): void
    {
        $input = $this->createInputStream([
            $this->buildRequest(1, 'initialize', ['rootPath' => __DIR__ . '/Fixture/Project']),
            $this->buildRequest(2, 'workspace/symbol'),
        ]);
        $output = fopen('php://memory', 'r+');

        $server = new LspServer();
        $server->run(new MessageStream($input, $output));

        rewind($output);
        $raw = stream_get_contents($output);

        $messages = $this->parseMessages($raw);

        $response = $messages[1];
        self::assertSame(2, $response['id']);

        $symbols = $response['result'];
        self::assertCount(6, $symbols);

        $home = $this->symbolByName($symbols, 'Route: app_home');
        self::assertNotNull($home);
        self::assertSame('GET|HEAD /', $home['detail']);
        self::assertSame(Uri::fromPath(__DIR__ . '/Fixture/Project/src/Controller/HomeController.php'), $home['location']['uri']);
        self::assertSame(11, $home['location']['range']['start']['line']);

        $legacy = $this->symbolByName($symbols, 'Route: app_legacy_home');
        self::assertNotNull($legacy);
        self::assertSame('ANY /legacy', $legacy['detail']);
    }

    public function testWorkspaceSymbolsRequestOnNonSymfonyProjectReturnsEmpty(): void
    {
        $input = $this->createInputStream([
            $this->buildRequest(1, 'initialize', ['rootPath' => __DIR__ . '/Fixture/Empty']),
            $this->buildRequest(2, 'workspace/symbol'),
        ]);
        $output = fopen('php://memory', 'r+');

        $server = new LspServer();
        $server->run(new MessageStream($input, $output));

        rewind($output);
        $raw = stream_get_contents($output);

        $messages = $this->parseMessages($raw);

        self::assertSame([], $messages[1]['result']);
    }

    public function testTextDocumentDefinitionReturnsControllerLocation(): void
    {
        $file = __DIR__ . '/Fixture/Project/src/RouteCalls.php';
        $source = (string) file_get_contents($file);
        $lines = explode("\n", $source);
        $line = 0;
        $character = 0;

        foreach ($lines as $index => $text) {
            $offset = strpos($text, 'app_post_show');

            if (false !== $offset) {
                $line = $index;
                $character = $offset + 5;

                break;
            }
        }

        $input = $this->createInputStream([
            $this->buildRequest(1, 'initialize', ['rootPath' => __DIR__ . '/Fixture/Project']),
            $this->buildRequest(2, 'textDocument/definition', [
                'textDocument' => ['uri' => Uri::fromPath($file)],
                'position' => ['line' => $line, 'character' => $character],
            ]),
        ]);
        $output = fopen('php://memory', 'r+');

        $server = new LspServer();
        $server->run(new MessageStream($input, $output));

        rewind($output);
        $raw = stream_get_contents($output);

        $messages = $this->parseMessages($raw);

        $response = $messages[1];
        self::assertSame(2, $response['id']);

        $locations = $response['result'];
        self::assertCount(1, $locations);
        self::assertSame(Uri::fromPath(__DIR__ . '/Fixture/Project/src/Controller/PostController.php'), $locations[0]['uri']);
        self::assertSame(16, $locations[0]['range']['start']['line']);
    }

    public function testTextDocumentDefinitionOnUnknownRouteReturnsEmpty(): void
    {
        $file = __DIR__ . '/Fixture/Project/src/RouteCalls.php';
        $source = (string) file_get_contents($file);
        $lines = explode("\n", $source);
        $line = 0;
        $character = 0;

        foreach ($lines as $index => $text) {
            $offset = strpos($text, 'app_not_defined');

            if (false !== $offset) {
                $line = $index;
                $character = $offset + 2;

                break;
            }
        }

        $input = $this->createInputStream([
            $this->buildRequest(1, 'initialize', ['rootPath' => __DIR__ . '/Fixture/Project']),
            $this->buildRequest(2, 'textDocument/definition', [
                'textDocument' => ['uri' => Uri::fromPath($file)],
                'position' => ['line' => $line, 'character' => $character],
            ]),
        ]);
        $output = fopen('php://memory', 'r+');

        $server = new LspServer();
        $server->run(new MessageStream($input, $output));

        rewind($output);
        $raw = stream_get_contents($output);

        $messages = $this->parseMessages($raw);

        self::assertSame([], $messages[1]['result']);
    }

    public function testDidSaveOnConfigRoutesFileRebuildsIndex(): void
    {
        $root = $this->tempRefreshRoot();
        $routesFile = $root . '/config/routes/routes.json';

        $server = $this->startRefreshServer($root);
        $this->writeRoutes($routesFile, ['app_dashboard', 'app_about']);

        $after = $this->runDidSaveAndSymbol($server, $routesFile, 3);
        self::assertCount(2, $after['result']);
        self::assertNotNull($this->symbolByName($after['result'], 'Route: app_about'));
    }

    public function testDidSaveOnControllerFileRebuildsIndex(): void
    {
        $root = $this->tempRefreshRoot();
        $routesFile = $root . '/config/routes/routes.json';
        $controllerFile = $root . '/src/Controller/DashboardController.php';

        $server = $this->startRefreshServer($root);
        $this->writeRoutes($routesFile, ['app_dashboard', 'app_about']);

        $after = $this->runDidSaveAndSymbol($server, $controllerFile, 3);
        self::assertCount(2, $after['result']);
    }

    public function testDidSaveOnUnrelatedFileDoesNotRebuildIndex(): void
    {
        $root = $this->tempRefreshRoot();
        $routesFile = $root . '/config/routes/routes.json';
        $serviceFile = $root . '/src/Service/FooService.php';

        $server = $this->startRefreshServer($root);
        $this->writeRoutes($routesFile, ['app_dashboard', 'app_about']);

        $after = $this->runDidSaveAndSymbol($server, $serviceFile, 3);
        self::assertCount(1, $after['result']);
    }

    public function testDidSaveOnBrokenProjectReturnsEmptyAndLogsError(): void
    {
        $root = __DIR__ . '/Fixture/Broken';
        $controllerFile = $root . '/src/Controller/DashboardController.php';

        $server = new LspServer();
        $output = fopen('php://memory', 'r+');
        $server->run(new MessageStream($this->createInputStream([
            $this->buildRequest(1, 'initialize', ['rootPath' => $root]),
        ]), $output));
        rewind($output);
        $this->parseMessages(stream_get_contents($output));

        $output = fopen('php://memory', 'r+');
        $server->run(new MessageStream($this->createInputStream([
            $this->buildNotification('textDocument/didSave', ['textDocument' => ['uri' => Uri::fromPath($controllerFile)]]),
            $this->buildRequest(3, 'workspace/symbol'),
        ]), $output));
        rewind($output);
        $after = $this->parseMessages(stream_get_contents($output));

        $response = $this->responseById($after, 3);
        self::assertNotNull($response);
        self::assertSame([], $response['result']);

        $log = $this->messageByMethod($after, 'window/logMessage');
        self::assertNotNull($log);
        self::assertSame(1, $log['params']['type']);
    }

    public function testDebugRouterFailureReturnsEmptyAndLogsError(): void
    {
        $input = $this->createInputStream([
            $this->buildRequest(1, 'initialize', ['rootPath' => __DIR__ . '/Fixture/Broken']),
            $this->buildRequest(2, 'workspace/symbol'),
        ]);
        $output = fopen('php://memory', 'r+');

        $server = new LspServer();
        $server->run(new MessageStream($input, $output));

        rewind($output);
        $raw = stream_get_contents($output);

        $messages = $this->parseMessages($raw);

        $response = $this->responseById($messages, 2);
        self::assertNotNull($response);
        self::assertSame([], $response['result']);

        $log = $this->messageByMethod($messages, 'window/logMessage');
        self::assertNotNull($log);
        self::assertSame(1, $log['params']['type']);
        self::assertStringContainsString('debug:router', $log['params']['message']);
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

    /**
     * @param list<array<string, mixed>> $messages
     *
     * @return array<string, mixed>|null
     */
    private function responseById(array $messages, int $id): ?array
    {
        foreach ($messages as $message) {
            if (($message['id'] ?? null) === $id) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $messages
     *
     * @return array<string, mixed>|null
     */
    private function messageByMethod(array $messages, string $method): ?array
    {
        foreach ($messages as $message) {
            if (($message['method'] ?? null) === $method) {
                return $message;
            }
        }

        return null;
    }

    private function startRefreshServer(string $root): LspServer
    {
        $server = new LspServer();
        $output = fopen('php://memory', 'r+');
        $server->run(new MessageStream($this->createInputStream([
            $this->buildRequest(1, 'initialize', ['rootPath' => $root]),
            $this->buildRequest(2, 'workspace/symbol'),
        ]), $output));
        rewind($output);
        $messages = $this->parseMessages(stream_get_contents($output));

        self::assertCount(1, $this->responseById($messages, 2)['result']);

        return $server;
    }

    /**
     * @return array<string, mixed>
     */
    private function runDidSaveAndSymbol(LspServer $server, string $file, int $requestId): array
    {
        $output = fopen('php://memory', 'r+');
        $server->run(new MessageStream($this->createInputStream([
            $this->buildNotification('textDocument/didSave', ['textDocument' => ['uri' => Uri::fromPath($file)]]),
            $this->buildRequest($requestId, 'workspace/symbol'),
        ]), $output));
        rewind($output);
        $messages = $this->parseMessages(stream_get_contents($output));

        return $this->responseById($messages, $requestId);
    }

    private function tempRefreshRoot(): string
    {
        $target = sys_get_temp_dir() . '/symfony-captain-refresh-' . bin2hex(random_bytes(6));
        mkdir($target, 0777, true);
        $source = __DIR__ . '/Fixture/Refresh';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $destination = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathname();

            if ($item->isDir()) {
                mkdir($destination);

                continue;
            }

            copy($item->getPathname(), $destination);
        }

        chmod($target . '/bin/console', 0755);

        $this->tempRoots[] = $target;

        return $target;
    }

    /**
     * @param list<string> $routeNames
     */
    private function writeRoutes(string $routesFile, array $routeNames): void
    {
        $routes = [];

        foreach ($routeNames as $index => $name) {
            $routes[$name] = [
                'path' => '/' . $name,
                'host' => 'ANY',
                'scheme' => 'ANY',
                'method' => 'GET',
                'class' => 'Symfony\\Component\\Routing\\Route',
                'defaults' => ['_controller' => 'App\\Controller\\DashboardController::index'],
                'requirements' => 'NO CUSTOM',
                'options' => [],
            ];
        }

        file_put_contents($routesFile, json_encode($routes, JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            $this->removeDirectory($root);
        }

        parent::tearDown();
    }

    private function removeDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildRequest(int $id, string $method, array $params = []): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildNotification(string $method, array $params = []): string
    {
        return json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params]);
    }

    /**
     * @param list<string> $messages
     * @return resource
     */
    private function createInputStream(array $messages)
    {
        $stream = fopen('php://memory', 'r+');
        foreach ($messages as $message) {
            fwrite($stream, sprintf("Content-Length: %d\r\n\r\n%s", strlen($message), $message));
        }
        rewind($stream);

        return $stream;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseLastMessage(string $raw): array
    {
        $messages = $this->parseMessages($raw);
        self::assertNotEmpty($messages);

        return array_pop($messages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseMessages(string $raw): array
    {
        $messages = [];
        $offset = 0;
        while ($offset < strlen($raw)) {
            $headersEnd = strpos($raw, "\r\n\r\n", $offset);
            if (false === $headersEnd) {
                break;
            }
            $headers = substr($raw, $offset, $headersEnd - $offset);
            if (!preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
                break;
            }
            $length = (int) $matches[1];
            $bodyStart = $headersEnd + 4;
            $body = substr($raw, $bodyStart, $length);
            $messages[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $offset = $bodyStart + $length;
        }

        return $messages;
    }
}
