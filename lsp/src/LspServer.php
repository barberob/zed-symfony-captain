<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class LspServer
{
    private ?RouteProvider $routeProvider;
    private ?RouteIndex $routeIndex = null;

    public function __construct(
        ?RouteProvider $routeProvider = null,
    ) {
        $this->routeProvider = $routeProvider;
    }

    public function run(MessageStream $stream): void
    {
        while (true) {
            $message = $stream->read();

            if (null === $message) {
                return;
            }

            $payload = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($payload)) {
                continue;
            }

            if (!$this->dispatch($payload, $stream)) {
                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function dispatch(array $message, MessageStream $stream): bool
    {
        $method = $message['method'] ?? null;

        if (!is_string($method)) {
            return true;
        }

        $id = $message['id'] ?? null;

        switch ($method) {
            case 'initialize':
                $this->initialize($message['params'] ?? [], $stream);
                $stream->write($this->encode([
                    'id' => $id,
                    'result' => [
                        'capabilities' => [
                            'workspaceSymbolProvider' => true,
                            'definitionProvider' => true,
                        ],
                    ],
                ]));

                return true;
            case 'initialized':
                return true;
            case 'shutdown':
                $stream->write($this->encode([
                    'id' => $id,
                    'result' => null,
                ]));

                return true;
            case 'exit':
                return false;
            case 'textDocument/didSave':
                $this->didSave($message['params'] ?? [], $stream);

                return true;
            case 'workspace/symbol':
                $this->ensureIndex($stream);
                $stream->write($this->encode([
                    'id' => $id,
                    'result' => $this->symbols(),
                ]));

                return true;
            case 'textDocument/definition':
                $this->ensureIndex($stream);
                $stream->write($this->encode([
                    'id' => $id,
                    'result' => $this->definition($message['params'] ?? []),
                ]));

                return true;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function initialize(array $params, MessageStream $stream): void
    {
        $root = $this->projectRoot($params);

        if (null === $this->routeProvider && null !== $root) {
            $this->routeProvider = new RouteProvider(
                $root,
                new DebugRouterParser(),
                new ControllerResolver($root),
            );
        }

        $this->rebuildIndex($stream);
    }

    private function ensureIndex(MessageStream $stream): void
    {
        if (null === $this->routeProvider || null !== $this->routeIndex) {
            return;
        }

        $this->rebuildIndex($stream);
    }

    private function rebuildIndex(MessageStream $stream): void
    {
        if (null === $this->routeProvider) {
            return;
        }

        if (!$this->routeProvider->isSymfonyProject()) {
            $this->routeIndex = null;

            return;
        }

        try {
            $this->routeIndex = new RouteIndex($this->routeProvider->build());
        } catch (RouteProviderException $exception) {
            $this->logError($stream, $exception->getMessage());
            $this->routeIndex = new RouteIndex([]);
        }
    }

    /**
     * Rebuilds the route index when a file under `src/Controller/` or
     * `config/routes/` is saved, so symbol and definition results stay in sync
     * with the workspace without a language server restart.
     *
     * @param array<string, mixed> $params
     */
    private function didSave(array $params, MessageStream $stream): void
    {
        $uri = $params['textDocument']['uri'] ?? null;

        if (!is_string($uri) || null === $this->routeProvider) {
            return;
        }

        $file = Uri::toPath($uri);

        if (null === $file || !$this->routeProvider->isRouteDefinitionFile($file)) {
            return;
        }

        $this->rebuildIndex($stream);
    }

    private function logError(MessageStream $stream, string $message): void
    {
        $stream->write($this->encode([
            'method' => 'window/logMessage',
            'params' => [
                'type' => 1,
                'message' => $message,
            ],
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function symbols(): array
    {
        if (null === $this->routeIndex) {
            return [];
        }

        return (new WorkspaceSymbols($this->routeProvider->projectRoot()))->build($this->routeIndex);
    }

    /**
     * Resolves a `textDocument/definition` request to the controller location
     * of the route name the cursor is on. Returns an empty list when the
     * cursor is not on a recognized route name string, when the route is
     * unknown, or when the controller cannot be resolved.
     *
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function definition(array $params): array
    {
        if (null === $this->routeIndex) {
            return [];
        }

        $uri = $params['textDocument']['uri'] ?? null;
        $position = $params['position'] ?? null;

        if (!is_string($uri) || !is_array($position)) {
            return [];
        }

        $file = Uri::toPath($uri);

        if (null === $file || !is_file($file) || !str_ends_with($file, '.php')) {
            return [];
        }

        $source = file_get_contents($file);

        if (false === $source) {
            return [];
        }

        $line = $position['line'] ?? null;
        $character = $position['character'] ?? null;

        if (!is_int($line) || !is_int($character)) {
            return [];
        }

        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character);

        if (null === $occurrence) {
            return [];
        }

        $entry = $this->routeIndex->findByName($occurrence->name);

        if (null === $entry || null === $entry->location) {
            return [];
        }

        return [[
            'uri' => Uri::fromPath($entry->location->file),
            'range' => [
                'start' => ['line' => $entry->location->line - 1, 'character' => 0],
                'end' => ['line' => $entry->location->line - 1, 'character' => 0],
            ],
        ]];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function projectRoot(array $params): ?string
    {
        foreach (['rootUri', 'rootPath'] as $key) {
            $value = $params[$key] ?? null;

            if (!is_string($value) || '' === $value) {
                continue;
            }

            return 'rootUri' === $key ? Uri::toPath($value) : $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        return json_encode(['jsonrpc' => '2.0'] + $payload);
    }
}
