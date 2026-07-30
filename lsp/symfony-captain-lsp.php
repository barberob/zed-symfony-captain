#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SymfonyCaptain\Lsp\LspServer;
use SymfonyCaptain\Lsp\MessageStream;

$server = new LspServer();
$server->run(new MessageStream(STDIN, STDOUT));
