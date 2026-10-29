<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('aggregate_type');
            $table->uuid('aggregate_id');
            $table->string('event_type');
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['event_type', 'created_at']);
        });

        DB::statement('CREATE INDEX outbox_events_pending_index ON outbox_events (available_at, created_at) WHERE published_at IS NULL AND last_error IS NULL');
        DB::statement('CREATE INDEX outbox_events_published_index ON outbox_events (published_at, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS outbox_events_pending_index');
        DB::statement('DROP INDEX IF EXISTS outbox_events_published_index');

        Schema::dropIfExists('outbox_events');
    }
};
