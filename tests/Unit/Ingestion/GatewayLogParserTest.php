<?php

namespace Tests\Unit\Ingestion;

use App\Exceptions\InvalidLogLineException;
use App\Services\Ingestion\GatewayLogParser;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GatewayLogParserTest extends TestCase
{
    public function test_it_parses_a_valid_line(): void
    {
        $log = (new GatewayLogParser)->parse(json_encode($this->payload()));

        $this->assertSame('72b34d31-4c14-3bae-9cc6-516a0939c9d6', $log->consumerId);
        $this->assertSame('c3e86413-648a-3552-90c3-b13491ee07d6', $log->serviceId);
        $this->assertSame('ritchie', $log->serviceName);
        $this->assertSame('GET', $log->requestMethod);
        $this->assertSame('/', $log->requestUri);
        $this->assertSame(500, $log->responseStatus);
        $this->assertSame(1836, $log->latencyProxy);
        $this->assertSame(8, $log->latencyGateway);
        $this->assertSame(1058, $log->latencyRequest);
        $this->assertSame('75.241.168.121', $log->clientIp);
        $this->assertSame(1566660387, $log->createdAt->getTimestamp());
    }

    public function test_it_rejects_malformed_json(): void
    {
        $this->expectException(InvalidLogLineException::class);
        $this->expectExceptionMessage('JSON malformado');

        (new GatewayLogParser)->parse('{"request":{"method":"GE');
    }

    public function test_it_rejects_json_that_is_not_an_object(): void
    {
        $this->expectException(InvalidLogLineException::class);
        $this->expectExceptionMessage('Campo ausente ou inválido [root].');

        (new GatewayLogParser)->parse('42');
    }

    #[DataProvider('requiredFields')]
    public function test_it_rejects_a_line_without_a_required_field(string $field): void
    {
        $payload = $this->payload();

        Arr::forget($payload, $field);

        $this->expectException(InvalidLogLineException::class);
        $this->expectExceptionMessage("Campo ausente ou inválido [{$field}].");

        (new GatewayLogParser)->parse(json_encode($payload));
    }

    #[DataProvider('requiredFields')]
    public function test_it_rejects_a_line_with_a_null_required_field(string $field): void
    {
        $payload = $this->payload();

        Arr::set($payload, $field, null);

        $this->expectException(InvalidLogLineException::class);
        $this->expectExceptionMessage("Campo ausente ou inválido [{$field}].");

        (new GatewayLogParser)->parse(json_encode($payload));
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_a_line_with_an_invalid_value(string $field, mixed $value): void
    {
        $payload = $this->payload();

        Arr::set($payload, $field, $value);

        $this->expectException(InvalidLogLineException::class);
        $this->expectExceptionMessage("Campo ausente ou inválido [{$field}].");

        (new GatewayLogParser)->parse(json_encode($payload));
    }

    /**
     * Todos os campos que o parser exige em uma linha de log.
     *
     * @return array<string, array{0: string}>
     */
    public static function requiredFields(): array
    {
        return [
            'consumer_id' => ['authenticated_entity.consumer_id.uuid'],
            'service.id' => ['service.id'],
            'service.name' => ['service.name'],
            'request.method' => ['request.method'],
            'request.uri' => ['request.uri'],
            'response.status' => ['response.status'],
            'latencies.proxy' => ['latencies.proxy'],
            'latencies.gateway' => ['latencies.gateway'],
            'latencies.request' => ['latencies.request'],
            'client_ip' => ['client_ip'],
            'started_at' => ['started_at'],
        ];
    }

    /**
     * Valores presentes, mas que não servem para o campo.
     *
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'texto vazio' => ['service.name', ''],
            'número em campo de texto' => ['client_ip', 123],
            'texto em campo numérico' => ['latencies.proxy', 'rápido'],
            'número negativo' => ['latencies.request', -1],
            'número decimal' => ['response.status', 200.5],
            'data como texto' => ['started_at', '2019-08-24'],
        ];
    }

    /**
     * Uma linha de log válida, com apenas os campos que o parser lê.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'request' => ['method' => 'GET', 'uri' => '/'],
            'response' => ['status' => 500],
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
        ];
    }
}
