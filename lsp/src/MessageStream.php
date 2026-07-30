<?php

declare(strict_types=1);

namespace SymfonyCaptain\Lsp;

final class MessageStream
{
    public function __construct(
        private readonly mixed $input,
        private readonly mixed $output,
    ) {
    }

    public function read(): ?string
    {
        $headers = '';

        while (!feof($this->input)) {
            $line = fgets($this->input);

            if (false === $line) {
                return null;
            }

            if ("\r\n" === $line) {
                break;
            }

            $headers .= $line;
        }

        if (feof($this->input) && '' === $headers) {
            return null;
        }

        if (!preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
            return null;
        }

        $length = (int) $matches[1];
        $body = '';

        while (strlen($body) < $length) {
            $chunk = fread($this->input, $length - strlen($body));

            if (false === $chunk || '' === $chunk) {
                return null;
            }

            $body .= $chunk;
        }

        return $body;
    }

    public function write(string $message): void
    {
        $payload = sprintf("Content-Length: %d\r\n\r\n%s", strlen($message), $message);

        fwrite($this->output, $payload);
        fflush($this->output);
    }
}
