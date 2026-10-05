<?php

namespace App\Services\Reports;

use App\Contracts\Report;
use App\Models\GatewayLog;

class RequestsByServiceReport implements Report
{
    public function filename(): string
    {
        return 'requests_by_service.csv';
    }

    public function headers(): array
    {
        return ['service_name', 'total_requests'];
    }

    /**
     * Total de requisições por serviço, do maior para o menor.
     */
    public function rows(): iterable
    {
        return GatewayLog::query()
            ->toBase()
            ->select('service_name')
            ->selectRaw('COUNT(*) AS total_requests')
            ->groupBy('service_name')
            ->orderByDesc('total_requests')
            ->orderBy('service_name')
            ->cursor()
            ->map(fn (object $row) => [$row->service_name, (int) $row->total_requests]);
    }
}
