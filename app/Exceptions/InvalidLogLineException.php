<?php

namespace App\Exceptions;

use JsonException;
use RuntimeException;

class InvalidLogLineException extends RuntimeException
{
    public static function malformedJson(JsonException $previous): self
    {
        return new self("JSON malformado: {$previous->getMessage()}.", previous: $previous);
    }

    public static function invalidField(string $field): self
    {
        return new self("Campo ausente ou inválido [{$field}].");
    }
}
