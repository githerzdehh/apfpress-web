<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['billing', 'shipping'])->default('shipping');
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('line_1');
            $table->string('line_2')->nullable();
            $table->string('city');
            $table->string('region');
            $table->string('postal_code', 20);
            $table->char('country', 2);
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'converted', 'abandoned'])->default('active')->index();
            $table->char('currency', 3)->default('CAD');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['cart_id', 'offering_id']);
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('country', 2);
            $table->json('regions')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('rate_amount')->default(0);
            $table->unsignedBigInteger('free_above_amount')->nullable();
            $table->unsignedBigInteger('minimum_order_amount')->nullable();
            $table->unsignedBigInteger('maximum_order_amount')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('country', 2);
            $table->string('region')->nullable();
            $table->string('tax_class')->default('standard');
            $table->string('label', 40);
            $table->unsignedInteger('rate_basis_points');
            $table->boolean('shipping_taxable')->default(false);
            $table->boolean('nexus_enabled')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['country', 'region', 'tax_class', 'active']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignUuid('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'paid', 'processing', 'fulfilled', 'cancelled', 'refunded', 'partially_refunded'])->default('pending')->index();
            $table->enum('payment_status', ['unpaid', 'authorized', 'paid', 'failed', 'refunded', 'partially_refunded'])->default('unpaid')->index();
            $table->enum('fulfillment_status', ['unfulfilled', 'processing', 'fulfilled', 'not_required'])->default('unfulfilled')->index();
            $table->string('email');
            $table->char('currency', 3)->default('CAD');
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->string('shipping_method')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('receipt_queued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->enum('kind', ['print_book', 'ebook', 'physical_product', 'digital_product', 'service']);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['stripe', 'paypal']);
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_checkout_id')->nullable()->index();
            $table->enum('status', ['created', 'pending', 'succeeded', 'failed', 'cancelled', 'refunded', 'partially_refunded'])->default('created')->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('CAD');
            $table->json('provider_metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_refund_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('reason')->nullable();
            $table->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['stripe', 'paypal']);
            $table->string('provider_event_id');
            $table->string('event_type');
            $table->json('payload');
            $table->enum('status', ['received', 'processed', 'ignored', 'failed'])->default('received');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('digital_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('digital_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('digital_asset_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'order_item_id', 'digital_asset_id'], 'digital_entitlements_unique');
        });

        Schema::create('download_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('digital_entitlement_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_events');
        Schema::dropIfExists('digital_entitlements');
        Schema::dropIfExists('digital_assets');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('addresses');
    }
};
