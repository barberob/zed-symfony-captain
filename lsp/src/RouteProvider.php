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
        $output = [];
        $exitCode = 0;

        exec($command . ' 2>/dev/null', $output, $exitCode);

        if (0 !== $exitCode) {
            throw new RouteProviderException(sprintf(
                'Command "bin/console debug:router" failed with exit code %d in project "%s".',
                $exitCode,
                $this->projectRoot,
            ));
        }

        return implode(PHP_EOL, $output);
    }
}
