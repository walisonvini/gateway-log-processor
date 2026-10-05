<?php

namespace Tests\Feature\Ingestion;

use App\DTOs\IngestionResult;
use App\Services\Ingestion\LogIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class IngestGatewayLogsCommandTest extends TestCase
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

    public function test_it_ingests_the_file_and_shows_the_totals(): void
    {
        $path = $this->createLogFile([$this->logLine(), $this->logLine()]);

        $this->artisan('logs:ingest', ['path' => $path])
            ->expectsOutputToContain('Ingestão concluída')
            ->expectsOutput('Linhas processadas: 2')
            ->expectsOutput('Linhas ignoradas: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('gateway_logs', 2);
    }

    public function test_it_warns_when_lines_were_skipped(): void
    {
        Log::spy();

        $path = $this->createLogFile([$this->logLine(), '{"request":{"method":"GE']);

        $this->artisan('logs:ingest', ['path' => $path])
            ->expectsOutput('Linhas processadas: 1')
            ->expectsOutput('Linhas ignoradas: 1')
            ->expectsOutput('As linhas ignoradas foram registradas no log da aplicação.')
            ->assertSuccessful();
    }

    public function test_it_fails_when_the_file_does_not_exist(): void
    {
        $path = sys_get_temp_dir().'/gateway-log-inexistente.txt';

        $this->artisan('logs:ingest', ['path' => $path])
            ->expectsOutput("Não foi possível ler o arquivo de log [{$path}].")
            ->assertFailed();
    }

    public function test_it_suggests_the_restart_option_when_the_file_was_replaced(): void
    {
        $path = $this->createLogFile([$this->logLine(['client_ip' => '10.0.0.1'])]);

        $this->artisan('logs:ingest', ['path' => $path])->assertSuccessful();

        // Outro arquivo assume o mesmo caminho.
        file_put_contents($path, $this->logLine(['client_ip' => '10.0.0.7'])."\n".$this->logLine(['client_ip' => '10.0.0.8'])."\n");

        $this->artisan('logs:ingest', ['path' => $path])
            ->expectsOutputToContain('não é o mesmo da última ingestão')
            ->expectsOutput('Use a opção --restart para processar o arquivo desde o início.')
            ->assertFailed();

        $this->assertDatabaseCount('gateway_logs', 1);

        $this->artisan('logs:ingest', ['path' => $path, '--restart' => true])
            ->expectsOutput('Linhas processadas: 2')
            ->assertSuccessful();

        $this->assertDatabaseCount('gateway_logs', 3);
    }

    public function test_it_rejects_a_batch_size_outside_the_allowed_range(): void
    {
        $path = $this->createLogFile([$this->logLine()]);

        foreach (['99', '1001', 'abc'] as $batch) {
            $this->artisan('logs:ingest', ['path' => $path, '--batch' => $batch])
                ->expectsOutput('A opção --batch deve ser um número inteiro entre 100 e 1000.')
                ->assertFailed();
        }

        $this->assertDatabaseCount('gateway_logs', 0);
    }

    public function test_it_passes_the_options_to_the_ingestion_service(): void
    {
        $this->mock(LogIngestionService::class)
            ->shouldReceive('ingest')
            ->once()
            ->with('/logs/log.txt', 250, true)
            ->andReturn(new IngestionResult(processed: 0, skipped: 0));

        $this->artisan('logs:ingest', ['path' => '/logs/log.txt', '--batch' => '250', '--restart' => true])
            ->assertSuccessful();
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
     * Monta uma linha de log com os campos que a ingestão lê.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function logLine(array $overrides = []): string
    {
        return json_encode(array_replace_recursive([
            'request' => ['method' => 'GET', 'uri' => '/'],
            'response' => ['status' => 200],
            'authenticated_entity' => [
                'consumer_id' => ['uuid' => '72b34d31-4c14-3bae-9cc6-516a0939c9d6'],
            ],
            'service' => [
                'id' => 'c3e86413-648a-3552-90c3-b13491ee07d6',
                'name' => 'ritchie',
            ],
            'latencies' => ['proxy' => 1836, 'gateway' => 8, 'request' => 1058],
            'client_ip' => '75.241.168.121',
            'started_at' => 1566660387,
        ], $overrides));
    }
}
