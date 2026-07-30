<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class LspServer
{
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
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        return json_encode(['jsonrpc' => '2.0'] + $payload);
    }
}
