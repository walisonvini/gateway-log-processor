<?php

namespace App\Services\Reports;

use App\Contracts\Report;
use App\Exceptions\ReportNotWritableException;
use Throwable;

class CsvReportWriter
{
    /**
     * Grava o relatório em um arquivo CSV no diretório informado e retorna o caminho gerado.
     *
     * As linhas são escritas uma a uma, sem manter o relatório inteiro em memória.
     *
     * @throws ReportNotWritableException
     */
    public function write(Report $report, string $directory): string
    {
        $this->ensureWritable($directory);

        $path = rtrim($directory, '/').'/'.$report->filename();

        // Escreve em um arquivo temporário e só troca pelo definitivo ao terminar:
        // se a geração falhar no meio, o relatório anterior continua intacto.
        $temporaryPath = $path.'.tmp';
        $handle = fopen($temporaryPath, 'w');

        try {
            fputcsv($handle, $report->headers(), escape: '');

            foreach ($report->rows() as $row) {
                fputcsv($handle, $row, escape: '');
            }
        } catch (Throwable $exception) {
            fclose($handle);
            unlink($temporaryPath);

            throw $exception;
        }

        fclose($handle);
        rename($temporaryPath, $path);

        return $path;
    }

    /**
     * @throws ReportNotWritableException
     */
    private function ensureWritable(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ReportNotWritableException::forDirectory($directory);
        }

        if (! is_writable($directory)) {
            throw ReportNotWritableException::forDirectory($directory);
        }
    }
}
