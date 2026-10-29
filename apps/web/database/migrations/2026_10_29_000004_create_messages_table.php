<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seq');
            $table->foreignUuid('sender_id')->constrained('users')->restrictOnDelete();
            $table->uuid('client_message_id');
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->uuid('reply_to_message_id')->nullable()->index();
            $table->uuid('forwarded_from_message_id')->nullable()->index();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['conversation_id', 'seq']);
            $table->unique(['sender_id', 'client_message_id']);
            $table->index(['conversation_id', 'deleted_at', 'seq']);
            $table->index(['sender_id', 'created_at']);
        });

        DB::statement('CREATE INDEX messages_conversation_seq_desc_index ON messages (conversation_id, seq DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS messages_conversation_seq_desc_index');

        Schema::dropIfExists('messages');
    }
};
