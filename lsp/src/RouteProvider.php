<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteProvider
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly DebugRouterParser $parser,
        private readonly ControllerResolver $controllerResolver,
    ) {
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    /**
     * Detects a Symfony project from its skeleton: a `bin/console` entry point
     * plus a bootable kernel marker (`src/Kernel.php` or `config/bundles.php`).
     * Actual bootability is verified by `debug:router` succeeding during
     * `build()`; a project with the skeleton but an unbootable kernel surfaces
     * as a `RouteProviderException` there instead of being treated as
     * non-Symfony.
     */
    public function isSymfonyProject(): bool
    {
        return is_file($this->projectRoot . '/bin/console')
            && (is_file($this->projectRoot . '/src/Kernel.php') || is_file($this->projectRoot . '/config/bundles.php'));
    }

    /**
     * Returns whether the given file can change the route index when saved:
     * controllers under `src/Controller/`, the `config/routes.yaml` file, or
     * route configuration under the `config/routes/` directory.
     */
    public function isRouteDefinitionFile(string $file): bool
    {
        $root = rtrim($this->projectRoot, '/') . '/';

        return str_starts_with($file, $root . 'src/Controller/')
            || $root . 'config/routes.yaml' === $file
            || $root . 'config/routes.yml' === $file
            || str_starts_with($file, $root . 'config/routes/');
    }

    /**
     * Returns the file watcher globs (relative to the project root) whose
     * changes can alter the route index. Mirrors `isRouteDefinitionFile()` so
     * the server can register for `workspace/didChangeWatchedFiles` and rebuild
     * on changes that never pass through the editor's `didSave`.
     *
     * @return list<string>
     */
    public function watchers(): array
    {
        return [
            'src/Controller/**/*.php',
            'config/routes.yaml',
            'config/routes.yml',
            'config/routes/**/*',
        ];
    }

    /**
     * Runs `bin/console debug:router --format=json` in the project root and
     * builds the route index, resolving each controller to its file location.
     *
     * @return list<RouteEntry>
     *
     * @throws RouteProviderException when the command fails or the project is not bootable
     */
    public function build(): array
    {
        $output = $this->runDebugRouter();

        $entries = [];

        foreach ($this->parser->parse($output) as $route) {
            $entries[] = new RouteEntry(
                route: $route,
                location: $this->controllerResolver->resolve($route->controller),
            );
        }

        return $entries;
    }

    private function runDebugRouter(): string
    {
        $command = sprintf(
            'cd %s && %s debug:router --format=json',
            escapeshellarg($this->projectRoot),
            escapeshellarg($this->projectRoot . '/bin/console'),
        );
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RouteProviderException(sprintf(
                'Command "bin/console debug:router" failed to start in project "%s".',
                $this->projectRoot,
            ));
        }

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);

        if (0 !== $exitCode) {
            $detail = '' === (string) $errorOutput ? '' : sprintf(' (%s)', trim((string) $errorOutput));

            throw new RouteProviderException(sprintf(
                'Command "bin/console debug:router" failed with exit code %d in project "%s"%s.',
                $exitCode,
                $this->projectRoot,
                $detail,
            ));
        }

        return (string) $output;
    }
}
