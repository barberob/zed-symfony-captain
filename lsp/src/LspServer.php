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
                $this->initialize($message['params'] ?? []);
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
            case 'workspace/symbol':
                $this->ensureIndex();
                $stream->write($this->encode([
                    'id' => $id,
                    'result' => $this->symbols(),
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

        if (null === $this->routeProvider && null !== $root) {
            $this->routeProvider = new RouteProvider(
                $root,
                new DebugRouterParser(),
                new ControllerResolver($root),
            );
        }

        $this->rebuildIndex();
    }

    private function ensureIndex(): void
    {
        if (null === $this->routeProvider || null !== $this->routeIndex) {
            return;
        }

        $this->rebuildIndex();
    }

    private function rebuildIndex(): void
    {
        if (null === $this->routeProvider) {
            return;
        }

        if (!$this->routeProvider->isSymfonyProject()) {
            $this->routeIndex = null;

            return;
        }

        $this->routeIndex = new RouteIndex($this->routeProvider->build());
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
