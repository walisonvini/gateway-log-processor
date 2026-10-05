<?php

namespace App\Console\Commands;

use App\Exceptions\LogFileChangedException;
use App\Exceptions\LogFileNotReadableException;
use App\Services\Ingestion\LogIngestionService;
use Illuminate\Console\Command;

class IngestGatewayLogs extends Command
{
    /**
     * Limites do lote, definidos por medição com 100 mil linhas: com 100 a ingestão
     * usa menos memória (2 MB) e é mais lenta; com 1.000 é mais rápida e usa 8 MB.
     */
    private const MIN_BATCH_SIZE = 100;

    private const MAX_BATCH_SIZE = 1000;

    /**
     * O nome e a assinatura do comando.
     *
     * @var string
     */
    protected $signature = 'logs:ingest
        {path : Caminho do arquivo de log do gateway (NDJSON)}
        {--batch=1000 : Quantidade de linhas inseridas por lote (de 100 a 1000)}
        {--restart : Descarta o checkpoint e processa o arquivo desde o início}';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Processa de forma incremental um arquivo de log do API Gateway';

    /**
     * Executa o comando.
     */
    public function handle(LogIngestionService $service): int
    {
        $path = $this->argument('path');
        $batchSize = filter_var($this->option('batch'), FILTER_VALIDATE_INT);

        if ($batchSize === false || $batchSize < self::MIN_BATCH_SIZE || $batchSize > self::MAX_BATCH_SIZE) {
            $this->error('A opção --batch deve ser um número inteiro entre '.self::MIN_BATCH_SIZE.' e '.self::MAX_BATCH_SIZE.'.');

            return self::FAILURE;
        }

        try {
            $result = $service->ingest($path, $batchSize, $this->option('restart'));
        } catch (LogFileNotReadableException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (LogFileChangedException $exception) {
            $this->error($exception->getMessage());
            $this->line('Use a opção --restart para processar o arquivo desde o início.');

            return self::FAILURE;
        }

        $this->info('Ingestão concluída.');
        $this->line("Linhas processadas: {$result->processed}");
        $this->line("Linhas ignoradas: {$result->skipped}");

        if ($result->skipped > 0) {
            $this->warn('As linhas ignoradas foram registradas no log da aplicação.');
        }

        return self::SUCCESS;
    }
}
