<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('consumer_id')->nullable()->index();
            $table->uuid('service_id');
            $table->string('service_name');
            $table->string('request_method', 10);
            $table->string('request_uri', 2048);
            $table->unsignedSmallInteger('response_status');
            $table->unsignedInteger('latency_proxy');
            $table->unsignedInteger('latency_gateway');
            $table->unsignedInteger('latency_request');
            $table->string('client_ip', 45);
            $table->timestamp('created_at');
            $table->timestamp('processed_at')->useCurrent();

            $table->index(
                ['service_name', 'latency_proxy', 'latency_gateway', 'latency_request'],
                'gateway_logs_service_latencies_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateway_logs');
    }
};
