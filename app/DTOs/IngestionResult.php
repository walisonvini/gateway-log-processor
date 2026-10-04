<?php

namespace App\DTOs;

final readonly class IngestionResult
{
    /**
     * @param  int  $processed  Quantidade de linhas inseridas no banco.
     * @param  int  $skipped  Quantidade de linhas inválidas ignoradas.
     */
    public function __construct(
        public int $processed,
        public int $skipped,
    ) {}
}
