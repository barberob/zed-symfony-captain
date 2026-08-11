<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class Uri
{
    public static function fromPath(string $path): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return 'file://' . $encoded;
    }

    public static function toPath(string $uri): ?string
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);

        if ('file' !== $scheme) {
            return null;
        }

        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path)) {
            return null;
        }

        return urldecode($path);
    }
}
