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
     * Timestamps a partir deste valor estão em milissegundos:
     * em segundos, ele só seria atingido no ano 5138.
     */
    private const MILLISECONDS_THRESHOLD = 100_000_000_000;

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
            consumerId: $this->consumerId($payload),
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
     * O consumidor vem como um UUID direto ou como um objeto que o contém,
     * e não existe em requisições não autenticadas.
     *
     * @param  array<string, mixed>  $payload
     */
    private function consumerId(array $payload): ?string
    {
        $consumer = Arr::get($payload, 'authenticated_entity.consumer_id');

        if (is_array($consumer)) {
            $consumer = $consumer['uuid'] ?? null;
        }

        if ($consumer === null) {
            return null;
        }

        if (! is_string($consumer) || $consumer === '') {
            throw InvalidLogLineException::invalidField('authenticated_entity.consumer_id');
        }

        return $consumer;
    }

    /**
     * O gateway informa o started_at em segundos ou em milissegundos.
     *
     * @param  array<string, mixed>  $payload
     */
    private function startedAt(array $payload): CarbonImmutable
    {
        $startedAt = $this->integer($payload, 'started_at');
        $timezone = date_default_timezone_get();

        return $startedAt >= self::MILLISECONDS_THRESHOLD
            ? CarbonImmutable::createFromTimestampMs($startedAt, $timezone)
            : CarbonImmutable::createFromTimestamp($startedAt, $timezone);
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
