<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class GatewayLogData
{
    public function __construct(
        public ?string $consumerId,
        public string $serviceId,
        public string $serviceName,
        public string $requestMethod,
        public string $requestUri,
        public int $responseStatus,
        public int $latencyProxy,
        public int $latencyGateway,
        public int $latencyRequest,
        public string $clientIp,
        public CarbonImmutable $createdAt,
    ) {}

    /**
     * Retorna a linha a ser inserida na tabela gateway_logs.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'consumer_id' => $this->consumerId,
            'service_id' => $this->serviceId,
            'service_name' => $this->serviceName,
            'request_method' => $this->requestMethod,
            'request_uri' => $this->requestUri,
            'response_status' => $this->responseStatus,
            'latency_proxy' => $this->latencyProxy,
            'latency_gateway' => $this->latencyGateway,
            'latency_request' => $this->latencyRequest,
            'client_ip' => $this->clientIp,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
