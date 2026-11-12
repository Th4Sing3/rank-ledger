<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->string('external_event_id', 128);
            $table->string('participant_id', 128);
            $table->bigInteger('points');
            $table->char('payload_fingerprint', 64);
            $table->timestampTz('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['leaderboard_id', 'external_event_id']);
            $table->index(['leaderboard_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_events');
    }
};
