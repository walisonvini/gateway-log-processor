<?php

namespace Database\Factories;

use App\Models\GatewayLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GatewayLog>
 */
class GatewayLogFactory extends Factory
{
    /**
     * Define o estado padrão do model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumer_id' => fake()->uuid(),
            'service_id' => fake()->uuid(),
            'service_name' => fake()->domainWord(),
            'request_method' => 'GET',
            'request_uri' => '/',
            'response_status' => 200,
            'latency_proxy' => fake()->numberBetween(800, 2000),
            'latency_gateway' => fake()->numberBetween(5, 20),
            'latency_request' => fake()->numberBetween(1000, 2500),
            'client_ip' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
