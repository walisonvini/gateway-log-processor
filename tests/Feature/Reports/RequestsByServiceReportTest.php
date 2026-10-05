<?php

namespace Tests\Feature\Reports;

use App\Models\GatewayLog;
use App\Services\Reports\RequestsByServiceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestsByServiceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_the_requests_of_each_service(): void
    {
        GatewayLog::factory()->count(3)->create(['service_name' => 'ritchie']);
        GatewayLog::factory()->count(2)->create(['service_name' => 'terry']);
        GatewayLog::factory()->create(['service_name' => 'orn']);

        $totals = [];

        foreach ((new RequestsByServiceReport)->rows() as [$service, $total]) {
            $totals[$service] = $total;
        }

        $this->assertEquals(['ritchie' => 3, 'terry' => 2, 'orn' => 1], $totals);
    }

    public function test_it_orders_the_services_by_total_and_then_by_name(): void
    {
        GatewayLog::factory()->count(2)->create(['service_name' => 'terry']);
        GatewayLog::factory()->count(3)->create(['service_name' => 'ritchie']);
        GatewayLog::factory()->count(2)->create(['service_name' => 'orn']);

        $rows = iterator_to_array((new RequestsByServiceReport)->rows(), false);

        // Maior total primeiro; no empate entre orn e terry, ordem alfabética.
        $this->assertSame([['ritchie', 3], ['orn', 2], ['terry', 2]], $rows);
    }

    public function test_it_returns_no_rows_when_there_are_no_logs(): void
    {
        $this->assertSame([], iterator_to_array((new RequestsByServiceReport)->rows(), false));
    }
}
