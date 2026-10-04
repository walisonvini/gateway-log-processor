<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewayLog extends Model
{
    /**
     * created_at comes from the log and processed_at from the database,
     * so Eloquent must not manage the timestamps.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
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
     * Get the attributes that should be cast.
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
