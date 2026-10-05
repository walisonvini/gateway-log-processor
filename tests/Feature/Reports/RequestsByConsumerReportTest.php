<?php

namespace Tests\Feature\Reports;

use App\Models\GatewayLog;
use App\Services\Reports\RequestsByConsumerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestsByConsumerReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_the_requests_of_each_consumer(): void
    {
        $first = '11111111-1111-4111-8111-111111111111';
        $second = '22222222-2222-4222-8222-222222222222';
        $third = '33333333-3333-4333-8333-333333333333';

        GatewayLog::factory()->count(3)->create(['consumer_id' => $first]);
        GatewayLog::factory()->count(2)->create(['consumer_id' => $second]);
        GatewayLog::factory()->create(['consumer_id' => $third]);

        $totals = [];

        foreach ((new RequestsByConsumerReport)->rows() as [$consumer, $total]) {
            $totals[$consumer] = $total;
        }

        $this->assertEquals([$first => 3, $second => 2, $third => 1], $totals);
    }

    public function test_it_orders_the_consumers_by_total_and_then_by_identifier(): void
    {
        $first = '11111111-1111-4111-8111-111111111111';
        $second = '22222222-2222-4222-8222-222222222222';
        $third = '33333333-3333-4333-8333-333333333333';

        GatewayLog::factory()->count(2)->create(['consumer_id' => $third]);
        GatewayLog::factory()->count(3)->create(['consumer_id' => $second]);
        GatewayLog::factory()->count(2)->create(['consumer_id' => $first]);

        $rows = iterator_to_array((new RequestsByConsumerReport)->rows(), false);

        // Maior total primeiro; no empate entre $first e $third, ordem do identificador.
        $this->assertSame([[$second, 3], [$first, 2], [$third, 2]], $rows);
    }

    public function test_it_returns_no_rows_when_there_are_no_logs(): void
    {
        $this->assertSame([], iterator_to_array((new RequestsByConsumerReport)->rows(), false));
    }
}
