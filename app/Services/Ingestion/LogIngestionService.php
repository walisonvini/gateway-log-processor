<?php

namespace App\Services\Ingestion;

use App\DTOs\IngestionResult;
use App\Exceptions\InvalidLogLineException;
use App\Exceptions\LogFileNotReadableException;
use App\Models\GatewayLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class LogIngestionService
{
    public function __construct(
        private readonly LogFileReader $logFileReader,
        private readonly GatewayLogParser $gatewayLogParser,
    ) {}

    /**
     * Lê o arquivo de log em lotes e insere cada lote no banco.
     *
     * @throws LogFileNotReadableException
     */
    public function ingest(string $path, int $batchSize = 10): IngestionResult
    {
        $processed = 0;
        $skipped = 0;

        $batches = LazyCollection::make(fn () => $this->logFileReader->lines($path))->chunk($batchSize);

        foreach ($batches as $batch) {
            $rows = [];

            foreach ($batch as $index => $line) {
                if ($line === '') {
                    continue;
                }

                try {
                    $rows[] = $this->gatewayLogParser->parse($line)->toArray();
                } catch (InvalidLogLineException $exception) {
                    $skipped++;

                    Log::warning('Linha de log inválida ignorada.', [
                        'arquivo' => $path,
                        'linha' => $index + 1,
                        'motivo' => $exception->getMessage(),
                    ]);
                }
            }

            if ($rows !== []) {
                GatewayLog::insert($rows);

                $processed += count($rows);
            }
        }

        return new IngestionResult($processed, $skipped);
    }
}
