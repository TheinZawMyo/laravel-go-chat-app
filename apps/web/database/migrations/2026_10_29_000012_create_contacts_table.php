<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('users')->cascadeOnDelete();
            $table->string('nickname')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'contact_id']);
            $table->index(['contact_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
