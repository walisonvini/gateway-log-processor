<?php

namespace App\Exceptions;

use RuntimeException;

class LogFileNotReadableException extends RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self("Não foi possível ler o arquivo de log [{$path}].");
    }
}
