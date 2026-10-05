<?php

namespace App\Services\Reports;

use App\Contracts\Report;
use App\Models\GatewayLog;

class RequestsByConsumerReport implements Report
{
    public function filename(): string
    {
        return 'requests_by_consumer.csv';
    }

    public function headers(): array
    {
        return ['consumer_id', 'total_requests'];
    }

    /**
     * Total de requisições por consumidor, do maior para o menor.
     */
    public function rows(): iterable
    {
        return GatewayLog::query()
            ->toBase()
            ->select('consumer_id')
            ->selectRaw('COUNT(*) AS total_requests')
            ->groupBy('consumer_id')
            ->orderByDesc('total_requests')
            ->orderBy('consumer_id')
            ->cursor()
            ->map(fn (object $row) => [$row->consumer_id, (int) $row->total_requests]);
    }
}
