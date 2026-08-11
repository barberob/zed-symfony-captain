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

    public function isSymfonyProject(): bool
    {
        return is_file($this->projectRoot . '/bin/console');
    }

    /**
     * Runs `bin/console debug:router --format=json` in the project root and
     * builds the route index, resolving each controller to its file location.
     * Returns an empty index when the command fails.
     *
     * @return list<RouteEntry>
     */
    public function build(): array
    {
        $output = $this->runDebugRouter();

        if (null === $output) {
            return [];
        }

        $entries = [];

        foreach ($this->parser->parse($output) as $route) {
            $entries[] = new RouteEntry(
                route: $route,
                location: $this->controllerResolver->resolve($route->controller),
            );
        }

        return $entries;
    }

    private function runDebugRouter(): ?string
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
            return null;
        }

        return implode(PHP_EOL, $output);
    }
}
