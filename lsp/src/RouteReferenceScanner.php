<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

/**
 * Scans the project's route reference files — PHP sources under `src/` and
 * Twig templates under `templates/` — for references to a given set of route
 * names, using the same per-language finders the editor features rely on.
 * Used to surface every usage of a route that was just removed.
 */
final class RouteReferenceScanner
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * Finds every reference to any of the given route names in the project.
     *
     * @param list<string> $names
     *
     * @return list<RouteReferenceLocation>
     */
    public function find(array $names): array
    {
        $wanted = array_flip($names);
        $locations = [];

        foreach ($this->files() as $file) {
            $finder = $this->finder($file);

            if (null === $finder) {
                continue;
            }

            $source = file_get_contents($file);

            if (false === $source) {
                continue;
            }

            foreach ($finder->find($source) as $occurrence) {
                if (!isset($wanted[$occurrence->name])) {
                    continue;
                }

                $locations[] = new RouteReferenceLocation(
                    name: $occurrence->name,
                    file: $this->relativePath($file),
                    line: Position::toPosition($source, $occurrence->startOffset)['line'] + 1,
                );
            }
        }

        return $locations;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = [];

        foreach (['src', 'templates'] as $subdirectory) {
            $directory = rtrim($this->projectRoot, '/') . '/' . $subdirectory;

            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $files[] = $item->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private function finder(string $file): ?RouteReferenceFinder
    {
        if (str_ends_with($file, '.php')) {
            return new RouteNameFinder();
        }

        if (str_ends_with($file, '.twig') || str_ends_with($file, '.twig.html')) {
            return new TwigRouteNameFinder();
        }

        return null;
    }

    private function relativePath(string $file): string
    {
        $root = rtrim($this->projectRoot, '/') . '/';

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }
}
