<?php

namespace App\Services\Ingestion;

use App\DTOs\IngestionResult;
use App\DTOs\LogLine;
use App\Exceptions\InvalidLogLineException;
use App\Exceptions\LogFileNotReadableException;
use App\Models\GatewayLog;
use App\Models\IngestionCheckpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class LogIngestionService
{
    public function __construct(
        private readonly LogFileReader $reader,
        private readonly GatewayLogParser $parser,
    ) {}

    /**
     * Lê o arquivo de log em lotes, a partir do ponto em que a última
     * execução parou, e insere cada lote no banco.
     *
     * @throws LogFileNotReadableException
     */
    public function ingest(string $path, int $batchSize = 1000): IngestionResult
    {
        $path = realpath($path) ?: $path;
        $checkpoint = IngestionCheckpoint::firstWhere('file_path', $path);
        $offset = $checkpoint?->byte_offset ?? 0;

        $processed = 0;
        $skipped = 0;

        $batches = LazyCollection::make(fn () => $this->reader->lines($path, $offset))->chunk($batchSize);

        foreach ($batches as $batch) {
            [$rows, $invalid, $endOffset] = $this->parseBatch($batch, $path);

            if ($endOffset === null) {
                break;
            }

            $checkpoint = $this->persistBatch($rows, $endOffset, $path, $checkpoint);

            $processed += count($rows);
            $skipped += $invalid;
        }

        return new IngestionResult($processed, $skipped);
    }

    /**
     * Converte as linhas do lote e informa até onde o arquivo foi consumido.
     *
     * @param  iterable<int, LogLine>  $batch
     * @return array{0: list<array<string, int|string|null>>, 1: int, 2: int|null}
     */
    private function parseBatch(iterable $batch, string $path): array
    {
        $rows = [];
        $invalid = 0;
        $endOffset = null;

        foreach ($batch as $line) {
            if ($line->content !== '') {
                try {
                    $rows[] = $this->parser->parse($line->content)->toArray();
                } catch (InvalidLogLineException $exception) {
                    // Linha sem quebra no final ainda está sendo escrita: fica para a próxima execução.
                    if (! $line->terminated) {
                        break;
                    }

                    $invalid++;

                    Log::warning('Linha de log inválida ignorada.', [
                        'arquivo' => $path,
                        'offset_final' => $line->endOffset,
                        'motivo' => $exception->getMessage(),
                    ]);
                }
            }

            $endOffset = $line->endOffset;
        }

        return [$rows, $invalid, $endOffset];
    }

    /**
     * Grava o lote e o checkpoint na mesma transação: ou os dois são salvos, ou nenhum.
     *
     * @param  list<array<string, int|string|null>>  $rows
     */
    private function persistBatch(array $rows, int $endOffset, string $path, ?IngestionCheckpoint $checkpoint): IngestionCheckpoint
    {
        $checkpoint ??= new IngestionCheckpoint([
            'file_path' => $path,
            'fingerprint' => $this->reader->fingerprint($path),
            'processed_lines' => 0,
        ]);

        DB::transaction(function () use ($rows, $endOffset, $checkpoint) {
            if ($rows !== []) {
                GatewayLog::insert($rows);
            }

            $checkpoint->byte_offset = $endOffset;
            $checkpoint->processed_lines += count($rows);
            $checkpoint->save();
        });

        return $checkpoint;
    }
}
