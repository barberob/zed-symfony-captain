<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class LspServer
{
    private ?RouteProvider $routeProvider;
    private ?RouteIndex $routeIndex = null;

    public function __construct(
        ?RouteProvider $routeProvider = null,
        private readonly Logger $logger = new Logger(),
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
        $this->logger->debug(sprintf('dispatch %s id=%s', $method, is_int($id) ? (string) $id : 'null'));

        switch ($method) {
            case 'initialize':
                $this->initialize($message['params'] ?? []);
                $stream->write($this->encode([
                    'id' => $id,
                    'result' => [
                        'capabilities' => [
                            'textDocumentSync' => [
                                'openClose' => true,
                                'change' => 0,
                                'save' => true,
                            ],
                            'workspaceSymbolProvider' => true,
                            'definitionProvider' => true,
                            'completionProvider' => [
                                'triggerCharacters' => ["'", '"'],
                            ],
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
            case 'textDocument/completion':
                $this->ensureIndex($stream);
                $stream->write($this->encode([
                    'id' => $id,
                    'result' => $this->completion($message['params'] ?? []),
                ]));

                return true;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function initialize(array $params): void
    {
        $root = $this->projectRoot($params);

        $this->logger->debug(sprintf('initialize root=%s', $root ?? '<null>'));

        if (null === $this->routeProvider && null !== $root) {
            $this->routeProvider = new RouteProvider(
                $root,
                new DebugRouterParser(),
                new ControllerResolver($root),
            );
        }
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
            $this->logger->debug(sprintf('rebuildIndex: %s is not a Symfony project', $this->routeProvider->projectRoot()));
            $this->routeIndex = null;

            return;
        }

        try {
            $entries = $this->routeProvider->build();
            $this->logger->debug(sprintf('rebuildIndex: built %d routes', count($entries)));
            $this->routeIndex = new RouteIndex($entries);
        } catch (RouteProviderException $exception) {
            $this->logger->error($exception->getMessage());
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
            $this->logger->debug(sprintf('didSave ignored uri=%s file=%s', $uri ?? 'null', $file ?? 'null'));

            return;
        }

        $this->logger->debug(sprintf('didSave triggers rebuild file=%s', $file));
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

        $position = $this->position($params);

        if (null === $position) {
            return [];
        }

        $source = $this->phpSource($params);

        if (null === $source) {
            return [];
        }

        [$line, $character] = $position;
        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character);

        if (null === $occurrence) {
            return [];
        }

        $entry = $this->routeIndex->findByName($occurrence->name);

        if (null === $entry || null === $entry->location) {
            return [];
        }

        return [$entry->location->toLocation()];
    }

    /**
     * Builds the `textDocument/completion` response for the cursor position:
     * every route name as a completion item when the cursor is on a route
     * reference, or an empty `CompletionList` otherwise. The result is never
     * an error.
     *
     * @param array<string, mixed> $params
     *
     * @return array{isIncomplete: false, items: list<array<string, mixed>>}
     */
    private function completion(array $params): array
    {
        if (null === $this->routeIndex) {
            return $this->emptyCompletionList();
        }

        $position = $this->position($params);
        $source = $this->phpSource($params);

        if (null === $position || null === $source) {
            return $this->emptyCompletionList();
        }

        [$line, $character] = $position;

        return [
            'isIncomplete' => false,
            'items' => (new RouteNameCompleter($this->routeIndex))->complete($source, $line, $character),
        ];
    }

    /**
     * @return array{isIncomplete: false, items: list<array<string, mixed>>}
     */
    private function emptyCompletionList(): array
    {
        return ['isIncomplete' => false, 'items' => []];
    }

    /**
     * Extracts the LSP position from a request, or null when it is missing or
     * malformed.
     *
     * @param array<string, mixed> $params
     *
     * @return array{0: int, 1: int}|null
     */
    private function position(array $params): ?array
    {
        $position = $params['position'] ?? null;

        if (!is_array($position)) {
            return null;
        }

        $line = $position['line'] ?? null;
        $character = $position['character'] ?? null;

        if (!is_int($line) || !is_int($character)) {
            return null;
        }

        return [$line, $character];
    }

    /**
     * Reads the source of the PHP file a request targets, or null when the
     * target is not an existing PHP file.
     *
     * @param array<string, mixed> $params
     */
    private function phpSource(array $params): ?string
    {
        $uri = $params['textDocument']['uri'] ?? null;

        if (!is_string($uri)) {
            return null;
        }

        $file = Uri::toPath($uri);

        if (null === $file || !is_file($file) || !str_ends_with($file, '.php')) {
            return null;
        }

        $source = file_get_contents($file);

        return false === $source ? null : $source;
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
