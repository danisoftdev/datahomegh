<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE fcm_tokens MODIFY token VARCHAR(512) NOT NULL');
        }

        Schema::table('fcm_tokens', function (Blueprint $table): void {
            $table->unique(['user_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::table('fcm_tokens', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'token']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE fcm_tokens MODIFY token TEXT NOT NULL');
        }
    }
};
