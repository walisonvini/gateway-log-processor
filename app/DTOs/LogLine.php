<?php

namespace App\DTOs;

final readonly class LogLine
{
    /**
     * @param  string  $content  Conteúdo da linha, sem a quebra de linha.
     * @param  int  $endOffset  Posição em bytes, no arquivo, logo após a linha.
     * @param  bool  $terminated  Se a linha termina com quebra de linha, ou seja, se foi escrita por completo.
     */
    public function __construct(
        public string $content,
        public int $endOffset,
        public bool $terminated,
    ) {}
}
