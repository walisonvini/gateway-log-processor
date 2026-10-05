<?php

namespace App\Contracts;

interface Report
{
    /**
     * Nome do arquivo CSV gerado para o relatório.
     */
    public function filename(): string;

    /**
     * Nomes das colunas, na ordem em que aparecem no arquivo.
     *
     * @return list<string>
     */
    public function headers(): array;

    /**
     * Linhas do relatório, entregues uma por vez, na mesma ordem das colunas.
     *
     * @return iterable<int, list<int|float|string>>
     */
    public function rows(): iterable;
}
