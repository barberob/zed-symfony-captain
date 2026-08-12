<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class Logger
{
    public function __construct(
        private readonly mixed $stream = null,
    ) {
    }

    public function debug(string $message): void
    {
        $this->write('debug', $message);
    }

    public function error(string $message): void
    {
        $this->write('error', $message);
    }

    private function write(string $level, string $message): void
    {
        $stream = $this->stream ?? STDERR;

        fwrite($stream, sprintf("[symfony-captain] %s %s: %s\n", $level, date('H:i:s'), $message));
        fflush($stream);
    }
}
