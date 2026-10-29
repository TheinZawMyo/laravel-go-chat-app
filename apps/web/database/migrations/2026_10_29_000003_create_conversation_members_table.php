<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_members', function (Blueprint $table) {
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->unsignedBigInteger('last_read_seq')->default(0);
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->primary(['conversation_id', 'user_id']);
            $table->index(['user_id', 'left_at', 'conversation_id']);
            $table->index(['conversation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_members');
    }
};
