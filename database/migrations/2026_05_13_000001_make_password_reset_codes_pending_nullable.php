<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE password_reset_codes MODIFY code_hash VARCHAR(255) NULL');
            DB::statement('ALTER TABLE password_reset_codes MODIFY expires_at TIMESTAMP NULL');

            return;
        }

        if ($driver === 'sqlite') {
            $rows = DB::table('password_reset_codes')->get();
            Schema::drop('password_reset_codes');
            Schema::create('password_reset_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('code_hash', 255)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('created_at')->useCurrent();
            });
            foreach ($rows as $row) {
                DB::table('password_reset_codes')->insert((array) $row);
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('UPDATE password_reset_codes SET code_hash = "" WHERE code_hash IS NULL');
            DB::statement('UPDATE password_reset_codes SET expires_at = created_at WHERE expires_at IS NULL');
            DB::statement('ALTER TABLE password_reset_codes MODIFY code_hash VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE password_reset_codes MODIFY expires_at TIMESTAMP NOT NULL');
        }
    }
};
