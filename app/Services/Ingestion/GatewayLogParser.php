<?php

namespace App\Services\Ingestion;

use App\DTOs\GatewayLogData;
use App\Exceptions\InvalidLogLineException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use JsonException;

class GatewayLogParser
{
    /**
     * Limites das colunas da tabela gateway_logs. Um valor fora deles faria o insert
     * do lote inteiro falhar, então a linha é rejeitada aqui, como linha inválida.
     */
    private const MAX_UUID_LENGTH = 36;

    private const MAX_SERVICE_NAME_LENGTH = 255;

    private const MAX_METHOD_LENGTH = 10;

    private const MAX_URI_LENGTH = 2048;

    private const MAX_IP_LENGTH = 45;

    private const MAX_STATUS = 65_535;

    private const MAX_LATENCY = 4_294_967_295;

    private const MAX_TIMESTAMP = 2_147_483_647;

    /**
     * Converte uma linha NDJSON em um log do gateway.
     *
     * @throws InvalidLogLineException
     */
    public function parse(string $line): GatewayLogData
    {
        try {
            $payload = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidLogLineException::malformedJson($exception);
        }

        if (! is_array($payload)) {
            throw InvalidLogLineException::invalidField('root');
        }

        return new GatewayLogData(
            consumerId: $this->string($payload, 'authenticated_entity.consumer_id.uuid', self::MAX_UUID_LENGTH),
            serviceId: $this->string($payload, 'service.id', self::MAX_UUID_LENGTH),
            serviceName: $this->string($payload, 'service.name', self::MAX_SERVICE_NAME_LENGTH),
            requestMethod: $this->string($payload, 'request.method', self::MAX_METHOD_LENGTH),
            requestUri: $this->string($payload, 'request.uri', self::MAX_URI_LENGTH),
            responseStatus: $this->integer($payload, 'response.status', self::MAX_STATUS),
            latencyProxy: $this->integer($payload, 'latencies.proxy', self::MAX_LATENCY),
            latencyGateway: $this->integer($payload, 'latencies.gateway', self::MAX_LATENCY),
            latencyRequest: $this->integer($payload, 'latencies.request', self::MAX_LATENCY),
            clientIp: $this->string($payload, 'client_ip', self::MAX_IP_LENGTH),
            createdAt: $this->startedAt($payload),
        );
    }

    /**
     * O gateway informa o started_at como timestamp Unix, em segundos.
     * O valor precisa caber em uma coluna TIMESTAMP, que vai de 1970 a 2038.
     *
     * @param  array<string, mixed>  $payload
     */
    private function startedAt(array $payload): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp(
            $this->integer($payload, 'started_at', self::MAX_TIMESTAMP, min: 1),
            date_default_timezone_get(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function string(array $payload, string $field, int $maxLength): string
    {
        $value = Arr::get($payload, $field);

        if (! is_string($value) || $value === '' || mb_strlen($value) > $maxLength) {
            throw InvalidLogLineException::invalidField($field);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function integer(array $payload, string $field, int $max, int $min = 0): int
    {
        $value = Arr::get($payload, $field);

        if (! is_int($value) || $value < $min || $value > $max) {
            throw InvalidLogLineException::invalidField($field);
        }

        return $value;
    }
}
