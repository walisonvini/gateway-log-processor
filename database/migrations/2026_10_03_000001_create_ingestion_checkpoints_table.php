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
        Schema::create('ingestion_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('file_path', 500)->unique();
            $table->char('fingerprint', 64);
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->unsignedBigInteger('processed_lines')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_checkpoints');
    }
};
