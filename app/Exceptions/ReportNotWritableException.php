<?php

namespace App\Exceptions;

use RuntimeException;

class ReportNotWritableException extends RuntimeException
{
    public static function forDirectory(string $directory): self
    {
        return new self("Não foi possível gravar relatórios no diretório [{$directory}].");
    }
}
