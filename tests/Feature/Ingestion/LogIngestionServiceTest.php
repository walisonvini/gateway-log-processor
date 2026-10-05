<?php

namespace Tests\Feature\Ingestion;

use App\Exceptions\LogFileChangedException;
use App\Exceptions\LogFileNotReadableException;
use App\Models\GatewayLog;
use App\Models\IngestionCheckpoint;
use App\Services\Ingestion\GatewayLogParser;
use App\Services\Ingestion\LogFileReader;
use App\Services\Ingestion\LogIngestionService;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class LogIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Arquivos temporários criados pelo teste, removidos no tearDown.
     *
     * @var list<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_it_stores_the_log_fields(): void
    {
        $path = $this->createLogFile([$this->logLine()]);

        $result = app(LogIngestionService::class)->ingest($path);

        $this->assertSame(1, $result->processed);
        $this->assertSame(0, $result->skipped);

        $this->assertDatabaseCount('gateway_logs', 1);
        $this->assertDatabaseHas('gateway_logs', [
            'consumer_id' => '72b34d31-4c14-3bae-9cc6-516a0939c9d6',
            'service_id' => 'c3e86413-648a-3552-90c3-b13491ee07d6',
            'service_name' => 'ritchie',
            'request_method' => 'GET',
            'request_uri' => '/',
            'response_status' => 500,
            'latency_proxy' => 1836,
            'latency_gateway' => 8,
            'latency_request' => 1058,
            'client_ip' => '75.241.168.121',
        ]);
    }

    public function test_it_stores_the_log_date_as_created_at(): void
    {
        // 1566660387 = 24/08/2019 15:26:27 (UTC)
        $path = $this->createLogFile([$this->logLine(['started_at' => 1566660387])]);

        app(LogIngestionService::class)->ingest($path);

        $this->assertSame('2019-08-24 15:26:27', GatewayLog::sole()->created_at->toDateTimeString());
    }

    public function test_it_stores_the_insertion_moment_as_processed_at(): void
    {
        // Log de 2019, processado agora.
        $path = $this->createLogFile([$this->logLine(['started_at' => 1566660387])]);
        $before = now()->startOfSecond();

        app(LogIngestionService::class)->ingest($path);

        $log = GatewayLog::sole();

        $this->assertTrue($log->processed_at->between($before, now()));
        $this->assertSame(2019, $log->created_at->year);
    }

    public function test_it_ingests_a_file_larger_than_the_batch_size(): void
    {
        // 25 linhas em lotes de 10: dois lotes cheios e um parcial.
        $lines = [];

        for ($i = 1; $i <= 25; $i++) {
            $lines[] = $this->logLine(['client_ip' => "10.0.0.{$i}"]);
        }

        $path = $this->createLogFile($lines);

        $result = app(LogIngestionService::class)->ingest($path, batchSize: 10);

        $this->assertSame(25, $result->processed);
        $this->assertSame(25, GatewayLog::distinct()->count('client_ip'));
        $this->assertDatabaseCount('gateway_logs', 25);

        $checkpoint = IngestionCheckpoint::sole();

        $this->assertSame(filesize($path), $checkpoint->byte_offset);
        $this->assertSame(25, $checkpoint->processed_lines);
    }

    public function test_it_does_not_duplicate_logs_when_run_again(): void
    {
        $path = $this->createLogFile([
            $this->logLine(),
            $this->logLine(),
            $this->logLine(),
        ]);

        $service = app(LogIngestionService::class);

        $first = $service->ingest($path);
        $second = $service->ingest($path);

        $this->assertSame(3, $first->processed);
        $this->assertSame(0, $second->processed);
        $this->assertDatabaseCount('gateway_logs', 3);
        $this->assertDatabaseCount('ingestion_checkpoints', 1);
    }

    public function test_it_ingests_only_the_lines_appended_since_the_last_run(): void
    {
        $path = $this->createLogFile([
            $this->logLine(['client_ip' => '10.0.0.1']),
            $this->logLine(['client_ip' => '10.0.0.2']),
        ]);

        $service = app(LogIngestionService::class);

        $first = $service->ingest($path);

        $this->appendToLogFile($path, [
            $this->logLine(['client_ip' => '10.0.0.3']),
            $this->logLine(['client_ip' => '10.0.0.4']),
        ]);

        $second = $service->ingest($path);

        $this->assertSame(2, $first->processed);
        $this->assertSame(2, $second->processed);

        // Cada linha aparece uma única vez, na ordem do arquivo.
        $this->assertSame(
            ['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4'],
            GatewayLog::orderBy('id')->pluck('client_ip')->all(),
        );

        $this->assertSame(4, IngestionCheckpoint::sole()->processed_lines);
    }

    public function test_it_resumes_from_the_last_saved_batch_after_a_failure(): void
    {
        $path = $this->createLogFile([
            $this->logLine(['client_ip' => '10.0.0.1']),
            $this->logLine(['client_ip' => '10.0.0.2']),
            $this->logLine(['client_ip' => '10.0.0.3']),
            $this->logLine(['client_ip' => '10.0.0.4']),
            $this->logLine(['client_ip' => '10.0.0.5']),
        ]);

        // Leitor que falha ao ler a 4ª linha: com lotes de 2, só o primeiro lote é gravado.
        $failingReader = new class extends LogFileReader
        {
            public function lines(string $path, int $offset = 0): Generator
            {
                $read = 0;

                foreach (parent::lines($path, $offset) as $line) {
                    if (++$read > 3) {
                        throw new RuntimeException('Falha simulada na leitura.');
                    }

                    yield $line;
                }
            }
        };

        try {
            (new LogIngestionService($failingReader, new GatewayLogParser))->ingest($path, batchSize: 2);

            $this->fail('A ingestão deveria ter sido interrompida.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha simulada na leitura.', $exception->getMessage());
        }

        $this->assertDatabaseCount('gateway_logs', 2);
        $this->assertSame(2, IngestionCheckpoint::sole()->processed_lines);

        $result = app(LogIngestionService::class)->ingest($path, batchSize: 2);

        $this->assertSame(3, $result->processed);

        // Nenhuma linha duplicada nem perdida.
        $this->assertSame(
            ['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4', '10.0.0.5'],
            GatewayLog::orderBy('id')->pluck('client_ip')->all(),
        );
    }

    public function test_it_does_not_keep_the_batch_when_the_checkpoint_cannot_be_saved(): void
    {
        $path = $this->createLogFile([$this->logLine(), $this->logLine()]);

        // Faz o salvamento do checkpoint falhar, depois de o lote já ter sido inserido.
        IngestionCheckpoint::saving(function () {
            throw new RuntimeException('Falha simulada ao salvar o checkpoint.');
        });

        try {
            app(LogIngestionService::class)->ingest($path);

            $this->fail('A ingestão deveria ter sido interrompida.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha simulada ao salvar o checkpoint.', $exception->getMessage());
        }

        $this->assertDatabaseCount('gateway_logs', 0);
        $this->assertDatabaseCount('ingestion_checkpoints', 0);
    }

    public function test_it_skips_invalid_lines_and_ingests_the_others(): void
    {
        Log::spy();

        $path = $this->createLogFile([
            $this->logLine(['client_ip' => '10.0.0.1']),
            '{"request":{"method":"GE',
            $this->logLine(['service' => ['name' => null]]),
            $this->logLine(['client_ip' => '10.0.0.2']),
        ]);

        $service = app(LogIngestionService::class);

        $result = $service->ingest($path);

        $this->assertSame(2, $result->processed);
        $this->assertSame(2, $result->skipped);

        $this->assertSame(
            ['10.0.0.1', '10.0.0.2'],
            GatewayLog::orderBy('id')->pluck('client_ip')->all(),
        );

        Log::shouldHaveReceived('warning')->twice();

        // As linhas inválidas ficam para trás: não são lidas de novo.
        $again = $service->ingest($path);

        $this->assertSame(0, $again->processed);
        $this->assertSame(0, $again->skipped);
    }

    public function test_it_leaves_an_unfinished_last_line_for_the_next_run(): void
    {
        Log::spy();

        $path = $this->createLogFile([
            $this->logLine(['client_ip' => '10.0.0.1']),
            $this->logLine(['client_ip' => '10.0.0.2']),
        ]);

        // O gateway começou a escrever a terceira linha, mas ainda não terminou (sem quebra de linha).
        $thirdLine = $this->logLine(['client_ip' => '10.0.0.3']);

        file_put_contents($path, substr($thirdLine, 0, 50), FILE_APPEND);

        $service = app(LogIngestionService::class);

        $first = $service->ingest($path);

        $this->assertSame(2, $first->processed);
        $this->assertSame(0, $first->skipped);
        $this->assertDatabaseCount('gateway_logs', 2);

        Log::shouldNotHaveReceived('warning');

        // O gateway termina de escrever a linha.
        file_put_contents($path, substr($thirdLine, 50)."\n", FILE_APPEND);

        $second = $service->ingest($path);

        $this->assertSame(1, $second->processed);
        $this->assertSame(
            ['10.0.0.1', '10.0.0.2', '10.0.0.3'],
            GatewayLog::orderBy('id')->pluck('client_ip')->all(),
        );
    }

    public function test_it_fails_when_the_file_is_smaller_than_what_was_already_processed(): void
    {
        $path = $this->createLogFile([
            $this->logLine(['client_ip' => '10.0.0.1']),
            $this->logLine(['client_ip' => '10.0.0.2']),
            $this->logLine(['client_ip' => '10.0.0.3']),
        ]);

        $service = app(LogIngestionService::class);

        $service->ingest($path);

        $offsetBefore = IngestionCheckpoint::sole()->byte_offset;

        // O arquivo é trocado por outro, com uma única linha.
        $this->replaceLogFile($path, [$this->logLine(['client_ip' => '10.0.0.9'])]);

        try {
            $service->ingest($path);

            $this->fail('A ingestão deveria ter sido interrompida.');
        } catch (LogFileChangedException $exception) {
            $this->assertStringContainsString('está menor do que o trecho já processado', $exception->getMessage());
        }

        $this->assertSame(
            ['10.0.0.1', '10.0.0.2', '10.0.0.3'],
            GatewayLog::orderBy('id')->pluck('client_ip')->all(),
        );
        $this->assertSame($offsetBefore, IngestionCheckpoint::sole()->byte_offset);
    }

    public function test_it_fails_when_the_file_was_replaced_by_another_one(): void
    {
        $path = $this->createLogFile([
            $this->logLine(['client_ip' => '10.0.0.1']),
            $this->logLine(['client_ip' => '10.0.0.2']),
        ]);

        $service = app(LogIngestionService::class);

        $service->ingest($path);

        $fingerprintBefore = IngestionCheckpoint::sole()->fingerprint;

        // Outro arquivo no mesmo caminho: maior que o anterior, mas começando por outra linha.
        $this->replaceLogFile($path, [
            $this->logLine(['client_ip' => '10.0.0.7']),
            $this->logLine(['client_ip' => '10.0.0.8']),
            $this->logLine(['client_ip' => '10.0.0.9']),
        ]);

        try {
            $service->ingest($path);

            $this->fail('A ingestão deveria ter sido interrompida.');
        } catch (LogFileChangedException $exception) {
            $this->assertStringContainsString('a primeira linha mudou', $exception->getMessage());
        }

        $this->assertSame(
            ['10.0.0.1', '10.0.0.2'],
            GatewayLog::orderBy('id')->pluck('client_ip')->all(),
        );
        $this->assertSame($fingerprintBefore, IngestionCheckpoint::sole()->fingerprint);
    }

    public function test_it_restarts_from_the_beginning_keeping_the_previous_logs(): void
    {
        $path = $this->createLogFile([
            $this->logLine(['client_ip' => '10.0.0.1']),
            $this->logLine(['client_ip' => '10.0.0.2']),
        ]);

        $service = app(LogIngestionService::class);

        $service->ingest($path);

        $fingerprintBefore = IngestionCheckpoint::sole()->fingerprint;

        // Um novo arquivo assume o mesmo caminho (rotação de log).
        $this->replaceLogFile($path, [
            $this->logLine(['client_ip' => '10.0.0.7']),
            $this->logLine(['client_ip' => '10.0.0.8']),
            $this->logLine(['client_ip' => '10.0.0.9']),
        ]);

        $result = $service->ingest($path, restart: true);

        $this->assertSame(3, $result->processed);

        // Os registros do arquivo anterior continuam, e os do novo são somados.
        $this->assertSame(
            ['10.0.0.1', '10.0.0.2', '10.0.0.7', '10.0.0.8', '10.0.0.9'],
            GatewayLog::orderBy('id')->pluck('client_ip')->all(),
        );

        $checkpoint = IngestionCheckpoint::sole();

        $this->assertNotSame($fingerprintBefore, $checkpoint->fingerprint);
        $this->assertSame(3, $checkpoint->processed_lines);

        // O novo arquivo passa a ser o de referência: a execução normal volta a funcionar.
        $this->assertSame(0, $service->ingest($path)->processed);
    }

    public function test_it_fails_when_the_file_does_not_exist(): void
    {
        $path = sys_get_temp_dir().'/gateway-log-inexistente.txt';

        try {
            app(LogIngestionService::class)->ingest($path);

            $this->fail('A ingestão deveria ter sido interrompida.');
        } catch (LogFileNotReadableException $exception) {
            $this->assertSame("Não foi possível ler o arquivo de log [{$path}].", $exception->getMessage());
        }

        $this->assertDatabaseCount('gateway_logs', 0);
        $this->assertDatabaseCount('ingestion_checkpoints', 0);
    }

    /**
     * Cria um arquivo de log temporário com uma linha NDJSON por item.
     *
     * @param  list<string>  $lines
     */
    private function createLogFile(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gateway-log-');

        file_put_contents($path, implode("\n", $lines)."\n");

        return $this->files[] = $path;
    }

    /**
     * Acrescenta linhas ao final de um arquivo de log, como o gateway faz.
     *
     * @param  list<string>  $lines
     */
    private function appendToLogFile(string $path, array $lines): void
    {
        file_put_contents($path, implode("\n", $lines)."\n", FILE_APPEND);
    }

    /**
     * Substitui o conteúdo de um arquivo de log, mantendo o mesmo caminho.
     *
     * @param  list<string>  $lines
     */
    private function replaceLogFile(string $path, array $lines): void
    {
        file_put_contents($path, implode("\n", $lines)."\n");
    }

    /**
     * Monta uma linha de log no formato gerado pelo gateway.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function logLine(array $overrides = []): string
    {
        return json_encode(array_replace_recursive([
            'request' => [
                'method' => 'GET',
                'uri' => '/',
                'url' => 'http://yost.com',
                'size' => 174,
                'querystring' => [],
                'headers' => ['accept' => '*/*', 'host' => 'yost.com', 'user-agent' => 'curl/7.37.1'],
            ],
            'upstream_uri' => '/',
            'response' => [
                'status' => 500,
                'size' => 878,
                'headers' => ['Content-Length' => '197', 'via' => 'gateway/1.3.0'],
            ],
            'authenticated_entity' => [
                'consumer_id' => ['uuid' => '72b34d31-4c14-3bae-9cc6-516a0939c9d6'],
            ],
            'route' => [
                'id' => '0636a119-b7ee-3828-ae83-5f7ebbb99831',
                'service' => ['id' => 'c3e86413-648a-3552-90c3-b13491ee07d6'],
            ],
            'service' => [
                'id' => 'c3e86413-648a-3552-90c3-b13491ee07d6',
                'name' => 'ritchie',
                'host' => 'ritchie.com',
            ],
            'latencies' => ['proxy' => 1836, 'gateway' => 8, 'request' => 1058],
            'client_ip' => '75.241.168.121',
            'started_at' => 1566660387,
        ], $overrides));
    }
}
