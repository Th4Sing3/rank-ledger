<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('leaderboard_id')->constrained()->cascadeOnDelete();
            $table->string('participant_id', 128);
            $table->bigInteger('score')->default(0);
            $table->unsignedInteger('event_count')->default(0);
            $table->timestampTz('last_event_at');
            $table->timestampsTz();

            $table->unique(['leaderboard_id', 'participant_id']);
            $table->index(['leaderboard_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};
