<?php

namespace App\Services\Ingestion;

use App\DTOs\LogLine;
use App\Exceptions\LogFileNotReadableException;
use Generator;

class LogFileReader
{
    /**
     * Lê o arquivo linha a linha a partir do offset informado,
     * mantendo apenas uma linha em memória por vez.
     *
     * @return Generator<int, LogLine>
     *
     * @throws LogFileNotReadableException
     */
    public function lines(string $path, int $offset = 0): Generator
    {
        $handle = $this->open($path);

        try {
            fseek($handle, $offset);

            while (($line = fgets($handle)) !== false) {
                yield new LogLine(
                    content: rtrim($line, "\r\n"),
                    endOffset: ftell($handle),
                    terminated: str_ends_with($line, "\n"),
                );
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Identifica o arquivo pelo hash da primeira linha,
     * que não muda enquanto o arquivo apenas cresce.
     *
     * @throws LogFileNotReadableException
     */
    public function fingerprint(string $path): string
    {
        $handle = $this->open($path);

        try {
            $firstLine = fgets($handle);
        } finally {
            fclose($handle);
        }

        return hash('sha256', rtrim((string) $firstLine, "\r\n"));
    }

    /**
     * Retorna o tamanho atual do arquivo em bytes.
     *
     * @throws LogFileNotReadableException
     */
    public function size(string $path): int
    {
        $this->ensureReadable($path);

        clearstatcache(true, $path);

        return filesize($path);
    }

    /**
     * @return resource
     *
     * @throws LogFileNotReadableException
     */
    private function open(string $path)
    {
        $this->ensureReadable($path);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw LogFileNotReadableException::forPath($path);
        }

        return $handle;
    }

    /**
     * @throws LogFileNotReadableException
     */
    private function ensureReadable(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw LogFileNotReadableException::forPath($path);
        }
    }
}
