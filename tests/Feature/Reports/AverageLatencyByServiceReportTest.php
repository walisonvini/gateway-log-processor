<?php

namespace Tests\Feature\Reports;

use App\Models\GatewayLog;
use App\Services\Reports\AverageLatencyByServiceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AverageLatencyByServiceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_averages_the_latencies_of_each_service_with_two_decimal_places(): void
    {
        GatewayLog::factory()
            ->count(3)
            ->sequence(
                ['latency_request' => 1000, 'latency_proxy' => 800, 'latency_gateway' => 10],
                ['latency_request' => 1001, 'latency_proxy' => 800, 'latency_gateway' => 10],
                ['latency_request' => 1001, 'latency_proxy' => 801, 'latency_gateway' => 10],
            )
            ->create(['service_name' => 'ritchie']);

        GatewayLog::factory()->create([
            'service_name' => 'terry',
            'latency_request' => 2000,
            'latency_proxy' => 1500,
            'latency_gateway' => 5,
        ]);

        $rows = iterator_to_array((new AverageLatencyByServiceReport)->rows(), false);

        // ritchie: request 3002 / 3 = 1000,666...; proxy 2401 / 3 = 800,333...; gateway 30 / 3 = 10.
        $this->assertSame([
            ['ritchie', '1000.67', '800.33', '10.00'],
            ['terry', '2000.00', '1500.00', '5.00'],
        ], $rows);
    }

    public function test_it_orders_the_services_by_name(): void
    {
        GatewayLog::factory()->create(['service_name' => 'terry']);
        GatewayLog::factory()->create(['service_name' => 'corkery']);
        GatewayLog::factory()->create(['service_name' => 'orn']);

        $services = [];

        foreach ((new AverageLatencyByServiceReport)->rows() as [$service]) {
            $services[] = $service;
        }

        $this->assertSame(['corkery', 'orn', 'terry'], $services);
    }

    public function test_it_returns_no_rows_when_there_are_no_logs(): void
    {
        $this->assertSame([], iterator_to_array((new AverageLatencyByServiceReport)->rows(), false));
    }
}
