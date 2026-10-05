<?php

namespace Tests\Feature\Reports;

use App\Models\GatewayLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateReportsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Diretório temporário onde o teste gera os relatórios, removido no tearDown.
     */
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/generate-reports-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_generates_the_three_reports(): void
    {
        GatewayLog::factory()->count(2)->create(['service_name' => 'ritchie']);
        GatewayLog::factory()->create(['service_name' => 'terry']);

        $this->artisan('reports:generate', ['--output' => $this->directory])
            ->expectsOutput("Relatório gerado: {$this->directory}/requests_by_consumer.csv")
            ->expectsOutput("Relatório gerado: {$this->directory}/requests_by_service.csv")
            ->expectsOutput("Relatório gerado: {$this->directory}/average_latency_by_service.csv")
            ->assertSuccessful();

        $this->assertSame(
            ['average_latency_by_service.csv', 'requests_by_consumer.csv', 'requests_by_service.csv'],
            $this->generatedFiles(),
        );

        $this->assertSame(
            "service_name,total_requests\nritchie,2\nterry,1\n",
            file_get_contents($this->directory.'/requests_by_service.csv'),
        );
    }

    public function test_it_generates_only_the_requested_report(): void
    {
        GatewayLog::factory()->create();

        $this->artisan('reports:generate', ['report' => 'requests-by-service', '--output' => $this->directory])
            ->expectsOutput("Relatório gerado: {$this->directory}/requests_by_service.csv")
            ->assertSuccessful();

        $this->assertSame(['requests_by_service.csv'], $this->generatedFiles());
    }

    public function test_it_fails_when_the_report_is_unknown(): void
    {
        $this->artisan('reports:generate', ['report' => 'nao-existe', '--output' => $this->directory])
            ->expectsOutput('Relatório desconhecido [nao-existe].')
            ->expectsOutput('Relatórios disponíveis: requests-by-consumer, requests-by-service, average-latency-by-service.')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->directory);
    }

    public function test_it_warns_when_there_are_no_logs(): void
    {
        $this->artisan('reports:generate', ['--output' => $this->directory])
            ->expectsOutput('Nenhum log encontrado no banco: os relatórios terão apenas o cabeçalho.')
            ->assertSuccessful();

        $this->assertSame(
            "service_name,total_requests\n",
            file_get_contents($this->directory.'/requests_by_service.csv'),
        );
    }

    public function test_it_fails_when_the_reports_cannot_be_written(): void
    {
        // Um arquivo ocupa o lugar onde o diretório de saída precisaria ser criado.
        mkdir($this->directory);
        file_put_contents($this->directory.'/ocupado', '');

        $output = $this->directory.'/ocupado/relatorios';

        $this->artisan('reports:generate', ['--output' => $output])
            ->expectsOutput("Não foi possível gravar relatórios no diretório [{$output}].")
            ->assertFailed();

        $this->assertSame(['ocupado'], $this->generatedFiles());
    }

    /**
     * Nomes dos arquivos gerados no diretório do teste, em ordem alfabética.
     *
     * @return list<string>
     */
    private function generatedFiles(): array
    {
        return array_values(array_diff(scandir($this->directory), ['.', '..']));
    }
}
