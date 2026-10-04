<?php

namespace App\Exceptions;

use RuntimeException;

class LogFileChangedException extends RuntimeException
{
    public static function truncated(string $path): self
    {
        return new self("O arquivo de log [{$path}] está menor do que o trecho já processado.");
    }

    public static function replaced(string $path): self
    {
        return new self("O arquivo de log [{$path}] não é o mesmo da última ingestão: a primeira linha mudou.");
    }
}
