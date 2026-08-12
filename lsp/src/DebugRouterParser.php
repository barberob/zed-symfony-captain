<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class DebugRouterParser
{
    /**
     * Parses the output of `bin/console debug:router --format=json`.
     *
     * The output is an object keyed by route name. The controller lives in
     * `defaults._controller`, the methods in the `method` field as a pipe
     * separated string (or `ANY`). Symfony's internal routes, whose names are
     * prefixed with `_` (`_wdt`, `_profiler`, `_preview_error`, …), are
     * excluded so the index only holds navigable application routes.
     *
     * @return list<Route>
     */
    public function parse(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $routes = [];

        foreach ($decoded as $name => $data) {
            if (!is_string($name) || str_starts_with($name, '_') || !is_array($data)) {
                continue;
            }

            $routes[] = new Route(
                name: $name,
                methods: $this->parseMethods($data['method'] ?? null),
                path: (string) ($data['path'] ?? ''),
                controller: (string) ($data['defaults']['_controller'] ?? ''),
            );
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function parseMethods(mixed $method): array
    {
        if (!is_string($method) || '' === $method || 'ANY' === $method) {
            return [];
        }

        return explode('|', $method);
    }
}
