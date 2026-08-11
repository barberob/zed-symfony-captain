<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class ControllerResolver
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * Maps a controller string such as `App\Controller\PostController::show`
     * to its file path and start line using the project autoloader and
     * reflection. Returns null when the controller cannot be resolved.
     */
    public function resolve(string $controller): ?RouteLocation
    {
        $parts = explode('::', $controller);

        if (2 !== count($parts)) {
            return null;
        }

        [$class, $method] = $parts;

        $this->loadProjectAutoloader();

        try {
            if (!class_exists($class)) {
                return null;
            }

            $reflection = new \ReflectionMethod($class, $method);
        } catch (\Throwable) {
            return null;
        }

        $file = $reflection->getFileName();

        if (false === $file || !is_file($file)) {
            return null;
        }

        return new RouteLocation($file, $reflection->getStartLine());
    }

    private function loadProjectAutoloader(): void
    {
        $autoload = $this->projectRoot . '/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }
    }
}
