<?php

namespace App\Services\Reports;

use App\Contracts\Report;
use App\Models\GatewayLog;

class AverageLatencyByServiceReport implements Report
{
    public function filename(): string
    {
        return 'average_latency_by_service.csv';
    }

    public function headers(): array
    {
        return ['service_name', 'avg_request_ms', 'avg_proxy_ms', 'avg_gateway_ms'];
    }

    /**
     * Latência média de cada serviço, em milissegundos, com duas casas decimais:
     * request é o tempo total da requisição, proxy o tempo no serviço final
     * e gateway o tempo de execução dos plugins.
     */
    public function rows(): iterable
    {
        return GatewayLog::query()
            ->toBase()
            ->select('service_name')
            ->selectRaw('ROUND(AVG(latency_request), 2) AS avg_request_ms')
            ->selectRaw('ROUND(AVG(latency_proxy), 2) AS avg_proxy_ms')
            ->selectRaw('ROUND(AVG(latency_gateway), 2) AS avg_gateway_ms')
            ->groupBy('service_name')
            ->orderBy('service_name')
            ->cursor()
            ->map(fn (object $row) => [
                $row->service_name,
                $row->avg_request_ms,
                $row->avg_proxy_ms,
                $row->avg_gateway_ms,
            ]);
    }
}
