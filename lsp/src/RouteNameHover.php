<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class RouteNameHover
{
    public function __construct(
        private readonly RouteIndex $index,
    ) {
    }

    /**
     * Builds the LSP `Hover` for a route name under the cursor, or null when
     * the cursor is not on a route reference or the route is unknown.
     *
     * @return array<string, mixed>|null
     */
    public function hover(string $source, int $line, int $character): ?array
    {
        $occurrence = (new RouteNameFinder())->findAt($source, $line, $character);

        if (null === $occurrence) {
            return null;
        }

        $entry = $this->index->findByName($occurrence->name);

        if (null === $entry) {
            return null;
        }

        return [
            'contents' => [
                'kind' => 'markdown',
                'value' => $this->markdown($entry->route),
            ],
            'range' => [
                'start' => Position::toPosition($source, $occurrence->startOffset),
                'end' => Position::toPosition($source, $occurrence->endOffset),
            ],
        ];
    }

    private function markdown(Route $route): string
    {
        $lines = [
            sprintf('**%s**', $route->name),
            sprintf('`%s`', $route->detail()),
        ];

        if ('' !== $route->controller) {
            $lines[] = sprintf('`%s`', $route->controller);
        }

        return implode("\n\n", $lines);
    }
}
