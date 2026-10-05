<?php

namespace App\Models;

use Database\Factories\GatewayLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GatewayLog extends Model
{
    /** @use HasFactory<GatewayLogFactory> */
    use HasFactory;

    /**
     * O created_at vem do log e o processed_at é gerado pelo banco,
     * então o Eloquent não deve gerenciar os timestamps.
     */
    public $timestamps = false;

    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'consumer_id',
        'service_id',
        'service_name',
        'request_method',
        'request_uri',
        'response_status',
        'latency_proxy',
        'latency_gateway',
        'latency_request',
        'client_ip',
        'created_at',
    ];

    /**
     * Retorna os atributos que devem ser convertidos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
