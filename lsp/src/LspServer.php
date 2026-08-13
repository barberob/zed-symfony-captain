<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class LspServer
{
    private ?RouteProvider $routeProvider;
    private ?RouteIndex $routeIndex = null;

    /** @var float Unix timestamp of the last `didSave`-triggered rebuild. */
    private float $lastDidSaveAt = 0.0;

    /** @var string|null File the last `didSave` rebuild covered. */
    private ?string $lastDidSaveFile = null;

    /** @var bool Whether the last route index build succeeded. Diagnostics are only published against a healthy index. */
    private bool $routeIndexHealthy = false;

    /** @var array<string, string> Open reference file URIs, re-published after an index rebuild. */
    private array $openDocuments = [];

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
                            'hoverProvider' => true,
                            'completionProvider' => [
                                'triggerCharacters' => ["'", '"'],
                            ],
                        ],
                    ],
                ]));

                return true;
            case 'initialized':
                $this->registerFileWatchers($stream);

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
            case 'textDocument/didOpen':
                $this->didOpen($message['params'] ?? [], $stream);

                return true;
            case 'textDocument/didClose':
                $this->didClose($message['params'] ?? []);

                return true;
            case 'workspace/didChangeWatchedFiles':
                $this->didChangeWatchedFiles($message['params'] ?? [], $stream);

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
            case 'textDocument/hover':
                $this->ensureIndex($stream);
                $stream->write($this->encode([
                    'id' => $id,
                    'result' => $this->hover($message['params'] ?? []),
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

    /**
     * Asks the client to watch the files whose changes can alter the route
     * index, so the server hears about route-definition changes (git pulls,
     * branch switches, codegen) that never pass through the editor's `didSave`.
     * Registered after `initialized` through `client/registerCapability`.
     */
    private function registerFileWatchers(MessageStream $stream): void
    {
        if (null === $this->routeProvider) {
            return;
        }

        $stream->write($this->encode([
            'id' => 'symfony-captain-register-route-watchers',
            'method' => 'client/registerCapability',
            'params' => [
                'registrations' => [
                    [
                        'id' => 'symfony-captain-route-files',
                        'method' => 'workspace/didChangeWatchedFiles',
                        'registerOptions' => [
                            'watchers' => array_map(
                                static fn (string $glob): array => ['globPattern' => $glob, 'kind' => 7],
                                $this->routeProvider->watchers(),
                            ),
                        ],
                    ],
                ],
            ],
        ]));
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
            $this->routeIndexHealthy = false;

            return;
        }

        try {
            $entries = $this->routeProvider->build();
            $this->logger->debug(sprintf('rebuildIndex: built %d routes', count($entries)));
            $this->routeIndex = new RouteIndex($entries);
            $this->routeIndexHealthy = true;
        } catch (RouteProviderException $exception) {
            $this->logger->error($exception->getMessage());
            $this->logError($stream, $exception->getMessage());
            $this->routeIndex = new RouteIndex([]);
            $this->routeIndexHealthy = false;
        }
    }

    /**
     * Rebuilds the route index when a file under `src/Controller/` or
     * `config/routes/` is saved, so symbol and definition results stay in sync
     * with the workspace without a language server restart. Saving a route
     * reference file re-publishes its diagnostics; saving a route-definition
     * file re-publishes every open reference file against the fresh index.
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

        if (null === $file) {
            return;
        }

        if ($this->routeProvider->isRouteDefinitionFile($file)) {
            $this->logger->debug(sprintf('didSave triggers rebuild file=%s', $file));
            $this->lastDidSaveAt = microtime(true);
            $this->lastDidSaveFile = $file;
            $this->rebuildIndex($stream);
            $this->publishOpenDocumentDiagnostics($stream);

            return;
        }

        if (null !== $this->routeReferenceFinder($file)) {
            $this->ensureIndex($stream);
            $this->publishDiagnostics($stream, $uri);

            return;
        }

        $this->logger->debug(sprintf('didSave ignored uri=%s file=%s', $uri, $file));
    }

    /**
     * Tracks an open reference file and publishes its diagnostics. Opening the
     * first document also triggers the eager route index build.
     *
     * @param array<string, mixed> $params
     */
    private function didOpen(array $params, MessageStream $stream): void
    {
        $uri = $params['textDocument']['uri'] ?? null;

        if (!is_string($uri)) {
            return;
        }

        $file = Uri::toPath($uri);

        if (null === $file || null === $this->routeReferenceFinder($file)) {
            return;
        }

        $this->openDocuments[$uri] = $uri;
        $this->ensureIndex($stream);
        $this->publishDiagnostics($stream, $uri);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function didClose(array $params): void
    {
        $uri = $params['textDocument']['uri'] ?? null;

        if (is_string($uri)) {
            unset($this->openDocuments[$uri]);
        }
    }

    /**
     * Publishes the diagnostics for a single open document: one warning per
     * dangling route reference. Silenced unless the last index build
     * succeeded, because a failed build collapses the index to empty and
     * flagging against it would mark every reference in the project as
     * dangling at once.
     */
    private function publishDiagnostics(MessageStream $stream, string $uri): void
    {
        if (null === $this->routeIndex || !$this->routeIndexHealthy) {
            return;
        }

        $file = Uri::toPath($uri);

        if (null === $file || !is_file($file)) {
            return;
        }

        $finder = $this->routeReferenceFinder($file);

        if (null === $finder) {
            return;
        }

        $source = file_get_contents($file);

        if (false === $source) {
            return;
        }

        $stream->write($this->encode([
            'method' => 'textDocument/publishDiagnostics',
            'params' => [
                'uri' => $uri,
                'diagnostics' => (new RouteDiagnostics($this->routeIndex, $finder))->diagnostics($source),
            ],
        ]));
    }

    /**
     * Re-publishes diagnostics for every open reference file after an index
     * rebuild, so a route added or removed on disk re-flags the files that
     * reference it without the user touching them.
     */
    private function publishOpenDocumentDiagnostics(MessageStream $stream): void
    {
        foreach ($this->openDocuments as $uri) {
            $this->publishDiagnostics($stream, $uri);
        }
    }

    /**
     * Rebuilds the route index when a watched file that can change routes
     * changes on disk, even when it is not open in the editor. Mirrors
     * `didSave` and covers changes the editor never sees. A saved file also
     * surfaces as a watched change, so a change event that echoes the last
     * `didSave` is skipped to avoid rebuilding twice per save.
     *
     * @param array<string, mixed> $params
     */
    private function didChangeWatchedFiles(array $params, MessageStream $stream): void
    {
        if (null === $this->routeProvider) {
            return;
        }

        $changes = $params['changes'] ?? null;

        if (!is_array($changes)) {
            return;
        }

        foreach ($changes as $change) {
            $uri = $change['uri'] ?? null;
            $type = $change['type'] ?? null;

            if (!is_string($uri) || !is_int($type)) {
                continue;
            }

            $file = Uri::toPath($uri);

            if (null === $file || !$this->routeProvider->isRouteDefinitionFile($file)) {
                continue;
            }

            if ($this->isDidSaveEcho($file, $type)) {
                $this->logger->debug(sprintf('didChangeWatchedFiles skipped as didSave echo file=%s', $file));

                continue;
            }

            $this->logger->debug(sprintf('didChangeWatchedFiles triggers rebuild file=%s', $file));
            $this->rebuildIndex($stream);
            $this->publishOpenDocumentDiagnostics($stream);

            return;
        }
    }

    /**
     * A saved file reaches the server twice: once as `textDocument/didSave`
     * and once as a watched change event. Rebuilding for both runs
     * `debug:router` twice per save, so a change event for the file the last
     * `didSave` just rebuilt is ignored.
     */
    private function isDidSaveEcho(string $file, int $type): bool
    {
        return 2 === $type
            && $file === $this->lastDidSaveFile
            && microtime(true) - $this->lastDidSaveAt < 0.5;
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

        $cursor = $this->cursor($params);

        if (null === $cursor) {
            return [];
        }

        [$line, $character, $source, $finder] = $cursor;
        $occurrence = $finder->findAt($source, $line, $character);

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

        $cursor = $this->cursor($params);

        if (null === $cursor) {
            return $this->emptyCompletionList();
        }

        [$line, $character, $source, $finder] = $cursor;

        return [
            'isIncomplete' => false,
            'items' => (new RouteNameCompleter($this->routeIndex, $finder))->complete($source, $line, $character),
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
     * Builds the `textDocument/hover` response for the cursor position: route
     * information when the cursor is on a known route reference, or null
     * otherwise. The result is never an error.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    private function hover(array $params): ?array
    {
        if (null === $this->routeIndex) {
            return null;
        }

        $cursor = $this->cursor($params);

        if (null === $cursor) {
            return null;
        }

        [$line, $character, $source, $finder] = $cursor;

        return (new RouteNameHover($this->routeIndex, $finder))->hover($source, $line, $character);
    }

    /**
     * Extracts the cursor context (line, character, source, finder) shared by
     * the definition, completion, and hover handlers, or null when the request
     * has no usable position or targets a file no finder understands.
     *
     * @param array<string, mixed> $params
     *
     * @return array{0: int, 1: int, 2: string, 3: RouteReferenceFinder}|null
     */
    private function cursor(array $params): ?array
    {
        $position = $this->position($params);

        if (null === $position) {
            return null;
        }

        $file = $this->targetFile($params);

        if (null === $file) {
            return null;
        }

        $finder = $this->routeReferenceFinder($file);

        if (null === $finder) {
            return null;
        }

        $source = file_get_contents($file);

        if (false === $source) {
            return null;
        }

        [$line, $character] = $position;

        return [$line, $character, $source, $finder];
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
     * Resolves the path of the file a request targets, or null when the target
     * is missing or does not exist on disk.
     *
     * @param array<string, mixed> $params
     */
    private function targetFile(array $params): ?string
    {
        $uri = $params['textDocument']['uri'] ?? null;

        if (!is_string($uri)) {
            return null;
        }

        $file = Uri::toPath($uri);

        return null !== $file && is_file($file) ? $file : null;
    }

    /**
     * Selects the route reference finder for a file by its extension, or null
     * when the server does not understand the language. PHP files use the
     * tokenizer-based finder; `.twig` and `.twig.html` files use the
     * lexer-based Twig finder.
     */
    private function routeReferenceFinder(string $file): ?RouteReferenceFinder
    {
        if (str_ends_with($file, '.php')) {
            return new RouteNameFinder();
        }

        if (str_ends_with($file, '.twig') || str_ends_with($file, '.twig.html')) {
            return new TwigRouteNameFinder();
        }

        return null;
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
