<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('direct');
            $table->string('title')->nullable();
            $table->string('avatar_key')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('direct_pair_key')->nullable()->unique();
            $table->uuid('last_message_id')->nullable()->index();
            $table->unsignedBigInteger('next_message_seq')->default(0);
            $table->timestamps();

            $table->index(['type', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
