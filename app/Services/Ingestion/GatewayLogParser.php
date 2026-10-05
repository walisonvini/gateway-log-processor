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
            consumerId: $this->string($payload, 'authenticated_entity.consumer_id.uuid'),
            serviceId: $this->string($payload, 'service.id'),
            serviceName: $this->string($payload, 'service.name'),
            requestMethod: $this->string($payload, 'request.method'),
            requestUri: $this->string($payload, 'request.uri'),
            responseStatus: $this->integer($payload, 'response.status'),
            latencyProxy: $this->integer($payload, 'latencies.proxy'),
            latencyGateway: $this->integer($payload, 'latencies.gateway'),
            latencyRequest: $this->integer($payload, 'latencies.request'),
            clientIp: $this->string($payload, 'client_ip'),
            createdAt: $this->startedAt($payload),
        );
    }

    /**
     * O gateway informa o started_at como timestamp Unix, em segundos.
     *
     * @param  array<string, mixed>  $payload
     */
    private function startedAt(array $payload): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp(
            $this->integer($payload, 'started_at'),
            date_default_timezone_get(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function string(array $payload, string $field): string
    {
        $value = Arr::get($payload, $field);

        if (! is_string($value) || $value === '') {
            throw InvalidLogLineException::invalidField($field);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function integer(array $payload, string $field): int
    {
        $value = Arr::get($payload, $field);

        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0) {
            throw InvalidLogLineException::invalidField($field);
        }

        return $value;
    }
}
