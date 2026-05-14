<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing MySQL databases that already ran the earlier "declined" enum migration
     * need this follow-up so agent self-registration can use status pending_payment.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('pending','pending_payment','active','held','declined','deleted') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('pending','active','held','declined','deleted') NOT NULL DEFAULT 'pending'");
    }
};
