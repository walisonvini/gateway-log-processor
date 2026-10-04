<?php

namespace App\Services\Ingestion;

use App\Exceptions\LogFileNotReadableException;
use Generator;

class LogFileReader
{
    /**
     * Lê o arquivo linha a linha, mantendo apenas uma linha em memória por vez.
     *
     * @return Generator<int, string>
     *
     * @throws LogFileNotReadableException
     */
    public function lines(string $path): Generator
    {
        $handle = $this->open($path);

        try {
            while (($line = fgets($handle)) !== false) {
                yield rtrim($line, "\r\n");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return resource
     *
     * @throws LogFileNotReadableException
     */
    private function open(string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw LogFileNotReadableException::forPath($path);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw LogFileNotReadableException::forPath($path);
        }

        return $handle;
    }
}
