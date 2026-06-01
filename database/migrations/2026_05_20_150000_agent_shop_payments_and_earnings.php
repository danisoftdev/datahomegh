<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('paystack_checkout_only')->default(false)->after('wallet_frozen');
            $table->string('payout_method', 32)->nullable()->after('paystack_checkout_only');
            $table->json('payout_details')->nullable()->after('payout_method');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_method', 16)->default('wallet')->after('amount');
            $table->string('paystack_reference')->nullable()->after('payment_method');
            $table->decimal('agent_cost_amount', 10, 2)->nullable()->after('paystack_reference');
            $table->decimal('agent_commission_amount', 10, 2)->nullable()->after('agent_cost_amount');
            $table->string('agent_commission_status', 16)->nullable()->after('agent_commission_amount');
        });

        Schema::create('agent_earnings_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('pending_withdrawal', 12, 2)->default(0);
            $table->timestamps();

            $table->unique('agent_id');
        });

        Schema::create('agent_earnings_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('withdrawal_request_id')->nullable();
            $table->string('type', 16);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('source', 64);
            $table->string('reference', 128)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['agent_id', 'id']);
        });

        Schema::create('agent_withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('status', 16)->default('PENDING');
            $table->string('payout_method', 32);
            $table->json('payout_details');
            $table->text('agent_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
        });

        Schema::table('agent_earnings_ledger', function (Blueprint $table): void {
            $table->foreign('withdrawal_request_id')
                ->references('id')
                ->on('agent_withdrawal_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_earnings_ledger', function (Blueprint $table): void {
            $table->dropForeign(['withdrawal_request_id']);
        });

        Schema::dropIfExists('agent_withdrawal_requests');
        Schema::dropIfExists('agent_earnings_ledger');
        Schema::dropIfExists('agent_earnings_balances');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_method',
                'paystack_reference',
                'agent_cost_amount',
                'agent_commission_amount',
                'agent_commission_status',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['paystack_checkout_only', 'payout_method', 'payout_details']);
        });
    }
};
