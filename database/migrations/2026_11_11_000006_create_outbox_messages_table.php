<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_client_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 100);
            $table->json('payload');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['published_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
