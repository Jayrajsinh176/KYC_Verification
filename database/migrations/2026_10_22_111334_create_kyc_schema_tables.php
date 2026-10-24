<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Retailers Table
        Schema::create('retailers', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. RT1001
            $table->string('shop');
            $table->string('owner');
            $table->string('city');
            $table->string('state');
            $table->string('mobile')->unique();
            $table->string('email')->unique();
            $table->string('password')->default(bcrypt('Demo@123'));
            $table->text('address')->nullable();
            $table->string('pan')->nullable();
            $table->string('gst')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // 2. KYC Services Table
        Schema::create('kyc_services', function (Blueprint $table) {
            $table->string('id')->primary(); // aadhaar, pan, bank, esign
            $table->string('name');
            $table->string('short');
            $table->text('desc');
            $table->string('icon');
            $table->decimal('provider_cost', 8, 2);
            $table->decimal('price', 8, 2);
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->timestamps();
        });

        // 3. Retailer Custom Pricing Overrides Table
        Schema::create('retailer_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('retailer_id');
            $table->string('service_id');
            $table->decimal('price', 8, 2);
            $table->timestamps();

            $table->foreign('retailer_id')->references('id')->on('retailers')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('kyc_services')->cascadeOnDelete();
            $table->unique(['retailer_id', 'service_id']);
        });

        // 4. Wallets Table
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('retailer_id')->unique();
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->string('currency', 3)->default('INR');
            $table->timestamps();

            $table->foreign('retailer_id')->references('id')->on('retailers')->cascadeOnDelete();
        });

        // 5. Wallet Ledger Transactions Table
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->string('id')->primary(); // TXN...
            $table->string('retailer_id');
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('ref');
            $table->string('mode')->default('Wallet');
            $table->text('note')->nullable();
            $table->string('status')->default('success');
            $table->string('by')->default('System');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('retailer_id')->references('id')->on('retailers')->cascadeOnDelete();
        });

        // 6. Fund Requests Table
        Schema::create('fund_requests', function (Blueprint $table) {
            $table->string('id')->primary(); // FRQ...
            $table->string('retailer_id');
            $table->decimal('amount', 10, 2);
            $table->string('mode');
            $table->string('utr')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->foreign('retailer_id')->references('id')->on('retailers')->cascadeOnDelete();
        });

        // 7. Verification Records Table
        Schema::create('verifications', function (Blueprint $table) {
            $table->string('id')->primary(); // VRF...
            $table->string('retailer_id');
            $table->string('type');
            $table->string('doc'); // masked document
            $table->string('name')->nullable();
            $table->enum('status', ['verified', 'failed', 'pending'])->default('verified');
            $table->decimal('amount', 8, 2);
            $table->text('response');
            $table->json('details')->nullable();
            $table->string('channel')->default('Web dashboard');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('retailer_id')->references('id')->on('retailers')->cascadeOnDelete();
        });

        // 8. Audit Logs Table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id')->primary(); // AUD...
            $table->string('actor');
            $table->string('action');
            $table->text('target')->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 9. System Settings Table
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('verifications');
        Schema::dropIfExists('fund_requests');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('retailer_pricing');
        Schema::dropIfExists('kyc_services');
        Schema::dropIfExists('retailers');
    }
};
