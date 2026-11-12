<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_client_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 128);
            $table->string('name', 160);
            $table->timestamps();

            $table->unique(['api_client_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboards');
    }
};
