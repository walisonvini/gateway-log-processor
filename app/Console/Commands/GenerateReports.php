<?php

namespace App\Console\Commands;

use App\Contracts\Report;
use App\Exceptions\ReportNotWritableException;
use App\Models\GatewayLog;
use App\Services\Reports\AverageLatencyByServiceReport;
use App\Services\Reports\CsvReportWriter;
use App\Services\Reports\RequestsByConsumerReport;
use App\Services\Reports\RequestsByServiceReport;
use Illuminate\Console\Command;

class GenerateReports extends Command
{
    /**
     * Relatórios disponíveis, pelo nome usado na linha de comando.
     *
     * @var array<string, class-string<Report>>
     */
    private const REPORTS = [
        'requests-by-consumer' => RequestsByConsumerReport::class,
        'requests-by-service' => RequestsByServiceReport::class,
        'average-latency-by-service' => AverageLatencyByServiceReport::class,
    ];

    /**
     * O nome e a assinatura do comando.
     *
     * @var string
     */
    protected $signature = 'reports:generate
        {report? : Relatório a gerar (requests-by-consumer, requests-by-service ou average-latency-by-service); sem ele, gera todos}
        {--output= : Diretório onde os arquivos CSV são gravados (padrão: storage/app/reports)}';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Gera os relatórios em CSV a partir dos logs do API Gateway';

    /**
     * Executa o comando.
     */
    public function handle(CsvReportWriter $writer): int
    {
        $name = $this->argument('report');

        if ($name !== null && ! array_key_exists($name, self::REPORTS)) {
            $this->error("Relatório desconhecido [{$name}].");
            $this->line('Relatórios disponíveis: '.implode(', ', array_keys(self::REPORTS)).'.');

            return self::FAILURE;
        }

        $reports = $name === null ? self::REPORTS : [$name => self::REPORTS[$name]];
        $directory = $this->option('output') ?? storage_path('app/reports');

        if (GatewayLog::query()->doesntExist()) {
            $this->warn('Nenhum log encontrado no banco: os relatórios terão apenas o cabeçalho.');
        }

        try {
            foreach ($reports as $report) {
                $path = $writer->write(app($report), $directory);

                $this->info("Relatório gerado: {$path}");
            }
        } catch (ReportNotWritableException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
