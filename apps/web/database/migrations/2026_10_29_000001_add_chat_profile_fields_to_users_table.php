<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique();
            $table->string('avatar_key')->nullable();
            $table->text('bio')->nullable();
            $table->string('status_text')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropIndex(['last_seen_at']);
            $table->dropSoftDeletes();
            $table->dropColumn(['username', 'avatar_key', 'bio', 'status_text', 'last_seen_at']);
        });
    }
};
