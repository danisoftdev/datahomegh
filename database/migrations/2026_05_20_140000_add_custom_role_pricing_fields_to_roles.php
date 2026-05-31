<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('available_at_registration')->default(false)->after('is_enabled');
            $table->string('pricing_persona', 16)->default('buyer')->after('available_at_registration');
            $table->boolean('requires_promotion_fee')->default(false)->after('pricing_persona');
            $table->decimal('promotion_fee', 10, 2)->nullable()->after('requires_promotion_fee');
        });

        DB::table('roles')->where('slug', 'buyer')->update([
            'available_at_registration' => true,
            'pricing_persona' => 'buyer',
        ]);

        DB::table('roles')->where('slug', 'agent')->update([
            'available_at_registration' => true,
            'pricing_persona' => 'agent',
        ]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn([
                'available_at_registration',
                'pricing_persona',
                'requires_promotion_fee',
                'promotion_fee',
            ]);
        });
    }
};
