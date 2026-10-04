<?php

namespace Tests\Feature\Ingestion;

use App\Models\GatewayLog;
use App\Services\Ingestion\LogIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
